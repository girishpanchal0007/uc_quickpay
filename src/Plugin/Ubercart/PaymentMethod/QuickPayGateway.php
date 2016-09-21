<?php

namespace Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uc_credit\CreditCardPaymentMethodBase;
use Drupal\uc_order\OrderInterface;
use Drupal\Component\Utility\Html;
use Drupal\uc_payment\PaymentReceiptInterface;
use Drupal\uc_quickpay\Entity\QuickPay;
use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayException;

/**
 * QuickPay Ubercart gateway payment method.
 *
 *
 * @UbercartPaymentMethod(
 *   id = "quickpay_gateway",
 *   name = @Translation("QuickPay"),
 * )
 */
class QuickPayGateway extends CreditCardPaymentMethodBase {

    /**
      * {@inheritdoc}
    */
    public function getDisplayLabel($label) {
        return ['#plain_text' => $label];
    }

    /**
     * Returns the set of card types which are used by this payment method.
     *
     * @return array
     *   An array with keys as needed by the chargeCard() method and values
     *   that can be displayed to the customer.
    */
    public function getEnabledTypes() {
        return [
            'visa' => $this->t('Visa'),
            'mastercard' => $this->t('MasterCard'),
            'discover' => $this->t('Discover'),
            'amex' => $this->t('American Express'),
        ];
    }

    /**
      * {@inheritdoc}
    */
    public function defaultConfiguration() {
        return parent::defaultConfiguration() + [
            'testmode'  => TRUE,
            'api' => [
                'merchant_id'  => '',
                'private_key'  => '',
                'agreement_id' => '',
                'api_key'      => '',
                'language'     => '',
                'currency'     => '',
                ],
            'callbacks' => [
                'continue_url' => '',
                'cancel_url'   => '',
                ]
        ];
    }
    /**
      * {@inheritdoc}
    */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['api'] = array(
            '#type' => 'details',
            '#title' => $this->t('API credentials'),
            '#description' => $this->t('@link for information on obtaining credentials. You need to acquire an API Signature. If you have already requested API credentials, you can review your settings under the API Access section of your QuickPayGateway profile.', ['@link' => Link::fromTextAndUrl($this->t('Click here'), Url::fromUri('http://tech.quickpay.net/api/'))->toString()]),
            '#open' => TRUE,
        );

        $form['api']['merchant_id'] = array(
            '#type' => 'textfield',
            '#title' => t('Merchant ID'),
            '#default_value' => $this->configuration['api']['merchant_id'],
            '#description' => t('The Merchant ID as shown in the QuickPay admin.'),
        );

        $form['api']['private_key'] = array(
            '#type' => 'textfield',
            '#title' => t('Private key'),
            '#default_value' => $this->configuration['api']['private_key'],
            '#description' => t('Your private key.'),
        );

        $form['api']['agreement_id'] = array(
            '#type' => 'textfield',
            '#title' => t('Agreement ID'),
            '#default_value' => $this->configuration['api']['agreement_id'],
            '#description' => t('This is the Payment Window Agreement ID.'),
        );

        $form['api']['api_key'] = array(
            '#type' => 'textfield',
            '#title' => t('API key'),
            '#default_value' => $this->configuration['api']['api_key'],
            '#description' => t('This is the Payment Window API key.'),
        );  
        
        $form['api']['pre_order_id'] = array(
            '#type' => 'textfield',
            '#title' => t('Order id prefix'),
            '#default_value' => $this->configuration['api']['pre_order_id'],
            '#description' => t('Prefix for order ids. Order ids must be uniqe when sent to QuickPay, use this to resolve clashes.'),
        ); 

        $form['language'] = array(
            '#type' => 'select',
            '#options' => array(
                    'da' => 'Danish',
                    'de' => 'German',
                    'en' => 'English',
                    'fr' => 'French',
                    'it' => 'Italian',
                    'no' => 'Norwegian',
                    'nl' => 'Dutch',
                    'pl' => 'Polish',
                    'se' => 'Swedish',
                ),
            '#title' => t('Select Language'),
            '#default_value' => $this->configuration['language'],
            '#description' => t('The language for the credit card form.'),
        );

        $form['currency'] = array(
            '#type' => 'select',
            '#options' => array(
                    'DKK' => 'DKK',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                    'SEK' => 'SEK',
                    'NOK' => 'NOK',
                    'GBP' => 'GBP',
                ),
            '#title' => t('Select Currency'),
            '#default_value' => $this->configuration['currency'],
            '#description' => t('Your currency.'),
        );

        $form['3d_secure'] = array(
            '#type' => 'checkbox',
            '#title' => t('3D Secure Creditcard'),
            '#description' => t('Checked 3D Secure Creditcard if you wish to make payment under 3D secure.'),
            '#default_value' => $this->configuration['3d_secure'],
        );        

        $form['testmode'] = array(
            '#type' => 'checkbox',
            '#title' => t('Test mode'),
            '#description' => 'When active, transactions will be run in test mode, even if the QuickPay account is in production mode. Order ids will get a T appended.',
            '#default_value' => $this->configuration['testmode'],
        );

        $form['callbacks'] = array(
            '#type' => 'details',
            '#title' => $this->t('CALLBACKS'),
            '#description' => $this->t('Quickpay callback urls.'),
            '#open' => TRUE,
        );

        $form['callbacks']['continue_url'] = array(
            '#type' => 'textfield',
            '#title' => t('Continue URL'),
            '#default_value' => $this->configuration['callbacks']['continue_url'],
            '#description' => t('After a successful transaction.'),
        );

        $form['callbacks']['cancel_url'] = array(
            '#type' => 'textfield',
            '#title' => t('Cancel URL'),
            '#default_value' => $this->configuration['callbacks']['cancel_url'],
            '#description' => t('If the user cancels the QuickPay transaction.'),    
        );

        return $form;
    }

    public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
        $elements = ['merchant_id', 'private_key', 'agreement_id', 'api_key', 'pre_order_id'];

        foreach ($elements as $element_name) {
            $raw_key = $form_state->getValue(['settings', 'api', $element_name]);
            $sanitized_key = $this->trimKey($raw_key);
            $form_state->setValue(['settings', $element_name], $sanitized_key);
            if (!$this->validateKey($form_state->getValue(['settings', $element_name]))) {
                $form_state->setError($form[$element_name], t('@name does not appear to be a valid QuickPay key', array('@name' => $element_name)));
            }
        }
        parent::validateConfigurationForm($form, $form_state);
    }

    protected function trimKey($key) {
        $key = trim($key);
        $key = \Drupal\Component\Utility\Html::escape($key);
        return $key;
    }
    /**
        * Validate QuickPay key
        *
        * @param $key
        * @return boolean
    */
    static public function validateKey($key) {
        $valid = preg_match('/^[a-zA-Z0-9_]+$/', $key);
        return $valid;
    }

    /**
        * {@inheritdoc}
    */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        foreach (['merchant_id', 'private_key', 'agreement_id', 'api_key', 'pre_order_id'] as $item) {
            $this->configuration['api'][$item] = $form_state->getValue(['settings', 'api', $item]);
        }
        $this->configuration['language'] = $form_state->getValue('language');
        $this->configuration['currency'] = $form_state->getValue('currency');
        $this->configuration['3d_secure'] = $form_state->getValue('3d_secure');
        $this->configuration['callbacks']['continue_url'] = $form_state->getValue(['settings', 'callbacks', 'continue_url']);
        $this->configuration['callbacks']['cancel_url'] = $form_state->getValue(['settings', 'callbacks', 'cancel_url']);

        return parent::submitConfigurationForm($form, $form_state);
    }
    // checkout form alter
    public function cartDetails(OrderInterface $order, array $form, FormStateInterface $form_state) {
        $form = parent::cartDetails($order, $form, $form_state);  

        // Send merchant and agreement ids to JS
        $form['#attached']['drupalSettings']['uc_quickpay']['merchant_id'] = $this->configuration['api']['merchant_id'];
        $form['#attached']['drupalSettings']['uc_quickpay']['agreement_id'] = $this->configuration['api']['agreement_id'];      

        //cc_exp_month  cc_exp_year cc_cvv data-quickpay="cardnumber"
        $form["cc_number"]['#attributes'] = array('data-quickpay' => 'cardnumber'); 

        unset($form['cc_exp_month']);
        unset($form['cc_exp_year']);

        //$form['actions'] = array('#type' => 'actions');
        // $form['quickpay_submit'] = array(
        //     "#type" => 'hidden',
        //     "#value" => 'Review order',
        //     "#attributes" => array(
        //         'id' => 'edit-continue',
        //         'autocomplete' => 'off',
        //     ),
        //     '#weight' => 15,
        // );
        $form['date_year'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Expiration date'),
            '#default_value' => '',
            '#attributes' => array(
                'autocomplete' => 'off',
                'data-quickpay' => 'expiration',
                'id' => 'cc-date-year',
                'placeholder' => 'MM / YY',
            ),
            '#required' => TRUE,
            '#weight' => 2,
        );
                   
        $form["cc_cvv"]['#attributes'] = array('data-quickpay' => 'cvd'); 

        return $form;
    }
    // on submit checkout form process
    public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state) {
        $quickpay_card_token = $_POST['card_token'];

        if (!empty($quickpay_card_token)) {
          \Drupal::service('user.private_tempstore')->get('uc_quickpay')->set('card_token', $quickpay_card_token);
        }
        return parent::cartProcess($order, $form, $form_state); // TODO: Change the autogenerated stub
    }

    /**
     * {@inheritdoc}
    */
    public function cartReviewTitle() {
        return $this->t('QuickPay Credit Card');
    }

    // alter cart-review from
    /**
      * {@inheritdoc}
    */
    public function cartReview(OrderInterface $order) {
        $fields = $this->getEnabledFields();
        
        if (!empty($fields['type'])) {
          $review[] = array('title' => $this->t('Card type'), 'data' => $order->payment_details['cc_type']);
        }
        if (!empty($fields['owner'])) {
          $review[] = array('title' => $this->t('Card owner'), 'data' => $order->payment_details['cc_owner']);
        }
        $review[] = array('title' => $this->t('Card number'), 'data' => $this->displayCardNumber($order->payment_details['cc_number']));     
        $review[] = array('title' => $this->t('Expiration'), 'data' => $order->payment_details['date_year']);
        return $review;
    }

    public function processPayment(OrderInterface $order, $amount, $txn_type, $reference = NULL) {
        // Ensure the cached details are loaded.
        // @todo Figure out which parts of this call are strictly necessary.
        $this->orderLoad($order);

        $result = $this->chargeCard($order, $amount, $txn_type, $reference);

        // If the payment processed successfully...
        if ($result['success'] === TRUE) {
            // Log the payment to the order if not disabled.
            if (!isset($result['log_payment']) || $result['log_payment'] !== FALSE) {
                uc_payment_enter($order->id(), $this->getPluginDefinition()['id'], $amount, empty($result['uid']) ? 0 : $result['uid'], empty($result['data']) ? '' : $result['data'], empty($result['comment']) ? '' : $result['comment']);
            }
        } else {
            // Otherwise display the failure message in the logs.
            \Drupal::logger('uc_payment')->warning('Payment failed for order @order_id: @message', ['@order_id' => $order->id(), '@message' => $result['message'], 'link' => $order->toLink($this->t('view order'))->toString()]);
        }

        return $result['success'];
    }

    // // on submit order review form process
    /**
    * {@inheritdoc}
    */
    protected function chargeCard(OrderInterface $order, $amount, $txn_type, $reference = NULL) {

        if (!$this->prepareApi()) {
            $result = array(
                'success' => FALSE,
                'comment' => t('QuickPay API not found.'),
                'message' => t('QuickPay API not found. Contact the site administrator.'),
                'uid' => $user->id(),
                'order_id' => $order->id(),
            );
            return $result;
        }

        // Product Detail.
        $productData = array();
        foreach ($order->products as $keys => $item) {
            $productData = array(
                'qty' => $item->qty->value,
                'item_no' => '',
                'item_name' => $item->title->value,
                'item_price' => uc_currency_format($item->price->value, FALSE, FALSE, '.'),
                'vat_rate' => true,
            );
        }
        
        $amount_currency = uc_currency_format($amount, FALSE, FALSE, FALSE);

        $card_token = \Drupal::service('user.private_tempstore')->get('uc_quickpay')->get('card_token');

        // Create payment to get payment_id
        $paymentform = array(
            'currency' => $order->getCurrency(),
            'order_id' => $this->configuration['api']['pre_order_id'] . $order->id(),
            'invoice_address' => [ 
                'email'    => $order->getEmail(), 
                'name'     => $order->getAddress('billing')->first_name .' '. $order->getAddress('billing')->last_name,
                'street'   => $order->getAddress('billing')->street1,
                'city'     => $order->getAddress('billing')->city,
                'zip_code' => $order->getAddress('billing')->postal_code,
                'region'   => $order->getAddress('billing')->zone,
                //'country_code'  => $order->getAddress('billing')->country,
                'phone_number'  => $order->getAddress('billing')->phone,
            ],
            'basket[]' => $productData,            
        );
        $payments = $this->client()->request->post('/payments', $paymentform);

        $payment = $payments->asObject();      
        $status = $payments->httpStatus();
        // checking status code
        if ($status == 201) {
            // Authorise the payment.
            $paymentdata = array(
                'amount' => $amount_currency,
                'card'   => [ 
                    'token' => $card_token,
                    'status' => isset($this->configuration['3d_secure'])? "true" : "false",

                ],
                'auto_capture' => false,
                'test_mode' => isset($this->configuration['testmode'])? 1 : 0,
                //'acquirer' => 'clearhaus',
            );

            $authorize_obj = $this->client()->request->post("/payments/{$payment->id}/authorize?synchronized", $paymentdata);
            
            $authorize_data = $authorize_obj->asObject();
            
            if ($authorize_obj->isSuccess()) {
                
                // call capture method to capture your payment.
                $payment_capture = $this->capture($payment->id, $amount_currency);
                
                $message = $this->t('QuickPay payment: @amount', ['@amount' => uc_currency_format($amount)]);

                uc_order_comment_save($order->id(), $order->getOwnerId(), $message, 'admin');         

                // it's store in payment_receipt.
                $extra_data = array('payment_token' => $quickpaytoken, 'response_payment_id' => $payment_capture->id);
                $serialize_data = serialize($extra_data);
                
                $result = array(
                    'success' => TRUE,
                    'comment' => $this->t('Payment charged,'),
                    'message' => $this->t('QuickPay credit card payment processed successfully.'),
                    'uid' => $order->getOwnerId(),
                    'data' => $serialize_data,
                );
                return $result;

            } else {
                $result = array(
                    'success' => FALSE,
                    'comment' => t("Payment capture is fail"),
                    'message' => t("Quickpay Payment is not capture for order !order: ", array('!order' => $order->id())),
                    'uid' => $order->getOwnerId(),       
                );

                \Drupal::logger('uc_quickpay')->notice($authorize_data->message);

                drupal_set_message('Error for authorizing quickpay payment : ' . $authorize_data->message, 'error', FALSE);
                
                uc_order_comment_save($order->id(), $order->getOwnerId(), $authorize_data->message, 'admin');

                return $result;
            }          
        
        }else{
            
            drupal_set_message('Error for creating quickpay payment : ' . $payment->message, 'error', FALSE);

            \Drupal::logger('uc_quickpay')->notice($payment->message);

            uc_order_comment_save($order->id(), $order->getOwnerId(), $payment->message, 'admin');

            return $result['success'] = FALSE;

        }

    }

    /**
      * Return Quickpay client.
      *
      * @return Quickpay
      *   The client.
    */
    public function client() {
        $api_key = $this->configuration['api']['api_key'];
        return new QuickPay(":{$api_key}");
    }

    /**
      * Capture on an authorised payment.
    */
    public function capture($order, $payment_id, $amount) {
        // Capture payment.
        $capturedata = array(
            'amount' => $amount,
        );
        
        $capture_res = $this->client()->request->post("/payments/{$payment_id}/capture?synchronized", $capturedata);

        $capture_data = $capture_res->asObject();

        if (!$capture_res->isSuccess()) {

            \Drupal::logger('uc_quickpay')->notice($capture_data->message);

            uc_order_comment_save($order->id(), $order->getOwnerId(), $capture_data->message, 'admin');
            
            $result = array(
                'success' => FALSE,
                'comment' => t("Payment capture is fail"),
                'message' => t("Quickpay Payment is not capture for order !order: !message", array(
                    '!order' => $order->id(), 
                    '!message' => $capture_data->message
                )),
                'uid' => $order->getOwnerId(),       
            );
            return $result;   
        }

        return $capture_data;
    }

    /**
      * Utility function: Load QuickPay API
      *
      * @return bool
    */
    public function prepareApi() {
        // Not clear that this is useful since payment config form forces at least some config
        if (!_uc_quickpay_check_api_keys($this->getConfiguration())) {
            \Drupal::logger('uc_quickpay')->error('QuickPay API keys are not configured. Payments cannot be made without them.', array());
            return FALSE;
        }
        return TRUE;
    }

    /**
      * Calculate the hash for the request.
      *
      * @param array $data
      *   The data to POST to Quickpay.
      *
      * @return string
      *   The checksum.
      *
      * @see http://tech.quickpay.net/payments/hosted/#checksum
    */
    protected function getChecksum(array $data, $api_key) {
        $flattened_params = $this->flattenParams($data);
        ksort($flattened_params);
        $base = implode(" ", $flattened_params);
        return hash_hmac("sha256", $base, $api_key);
    }

    /**
      * Flatten request parameter array.
    */
    protected function flattenParams($obj, $result = array(), $path = array()) {
        if (is_array($obj)) {
            foreach ($obj as $k => $v) {
                $result = array_merge($result, $this->flattenParams($v, $result, array_merge($path, array($k))));
            }
        }
        else {
            $result[implode("", array_map(function($param) {
                return "[{$param}]";
            }, $path))] = $obj;
        }

        return $result;
    }

}