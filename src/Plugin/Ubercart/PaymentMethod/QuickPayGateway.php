<?php

namespace Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uc_credit\CreditCardPaymentMethodBase;
use Drupal\uc_order\OrderInterface;
use Drupal\Component\Utility\Html;
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
        return $this->t('QuickPay');
    }

    // alter cart-review from
    /**
      * {@inheritdoc}
    */
    public function cartReview(OrderInterface $order) {
        // Format the amount in cents, which is what QuickPay wants
        //$order->getPaymentMethodId();
        //$order->getSubtotal();
       
        //$libraries_path = libraries_get_path('QuickPay');
        //include('/var/www/ecomm/modules/custom/uc_quickpay/lib/QuickPay/QuickPay.php');

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
        $user = \Drupal::currentUser();
        global $base_url;  

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
        
        $curcy_amount = uc_currency_format($amount, FALSE, FALSE, FALSE);

        $quickpaytoken = \Drupal::service('user.private_tempstore')->get('uc_quickpay')->get('card_token');

        try {

            $api_key = $this->configuration['api']['api_key'];
            $client = new QuickPay(":{$api_key}");

        } catch (QuickPayException $e) {
            $result = array(
                'success' => FALSE,
                'comment' => $e->getCode(),
                'message' => t("QuickPay client creation Failed for order !order: !message", array(
                    "!order" => $order->id(),
                    "!message" => $e->getMessage()
                )),
                'uid' => $user->id(),
                'order_id' => $order->id(),
            );
            uc_order_comment_save($order->id(), $user->id(), $result['message']);

            \Drupal::logger('uc_quickpay')
              ->notice('Failed QuickPay Client creation: @message', array('@message' => $result['message']));
            $message = $this->t('QuickPay payment failed.');
            uc_order_comment_save($order->id(), $user->id(), $message, 'admin');
            return $result;
        }

        // Create payment
        $quickpayform = array(
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
        $payments = $client->request->post('/payments', $quickpayform);
        $status = $payments->httpStatus();
        $payment = $payments->asObject();
        
        if (!$payments->isSuccess()) {
            drupal_set_message('Error for creating quickpay payment : ' . $payment->message, 'error', FALSE);
            \Drupal::logger('uc_quickpay')->notice($payment->message);
            uc_order_comment_save($order->id(), $user->id(), $authorize_res->message, 'admin');
        }

        if ($status == 201) {
            // Successful created
            $payment = $payments->asObject();
            // Store the Payment ID in temp storage,
            // We'll pick it up later to save it in the database since we might not have a $user object at this point anyway
            \Drupal::service('user.private_tempstore')->get('uc_quickpay')->set('uc_quickpay_payment_id', $payment->id);

            // Authorise the payment.
            $paymentdata = array(
                'amount' => $curcy_amount,
                'card'   => [ 
                    'token' => $quickpaytoken,
                    'status' => false,

                ],
                'auto_capture' => false,
                //'test_mode' => isset($this->configuration['testmode'])? '1' : '0';,
                //'acquirer' => 'clearhaus',
                //'card[number]' => $cardnumber,
                //'card[expiration]' => $expiration,
                //'card[cvd]' => $cvd,
                //'auto_capture' => isset($options['autocapture']) && $options['autocapture'],
            );

            $authorize_res = $client->request->post("/payments/{$payment->id}/authorize?synchronized", $paymentdata);

            $payments_res = $authorize_res->asObject();
            
            if (!$authorize_res->isSuccess()) {
                \Drupal::logger('uc_quickpay')->notice($payments_res->message);

                drupal_set_message('Error for authorizing QuickPay payment : ' . $authorize_res->message, 'error', FALSE);
                uc_order_comment_save($order->id(), $user->id(), $authorize_res->message, 'admin');
            }
        
            $message = $this->t('QuickPay payment: @amount', ['@amount' => uc_currency_format($amount)]);

            uc_order_comment_save($order->id(), $user->id(), $message, 'admin');
            // Change Order Status "in_checkout to Payment received"
            //$order->setStatusId('payment_received')->save();           

            // it's store in payment_receipt.
            $payment_data = array('payment_token' => $quickpaytoken, 'response_payment_id' => $payment->id);
            $serialize_data = serialize($payment_data);
            
            $result = array(
                'success' => TRUE,
                'comment' => $this->t('Payment charged,'),
                'message' => $this->t('QuickPay Credit card payment processed successfully.'),
                'uid' => $user->id(),
                'data' => $serialize_data,
            );
            return $result;
        }

    }

    // /**
    //  * Return Quickpay client.
    //  *
    //  * @return Quickpay\Quickpay
    //  *   The client.
    // */
    // public function client() {
    //     module_load_include('php', 'uc_quickpay', 'lib/QuickPay/QuickPay');
    //     return new Quickpay(':' . $this->configuration['api']['api_key']);
    // }

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

    /**
     * Retrieve the QuickPay customer id for a user
     *
     * @param $uid
     * @return string|NULL
    */
    // function getClientID($uid) {
    //     /** @var \Drupal\user\UserDataInterface $userdata_container */
    //     $userdata_container = \Drupal::getContainer('user.data');

    //     $id = $userdata_container->get('uc_quickpay', $uid, 'uc_quickpay_payment_id');

    //     return $id;
    // }
}