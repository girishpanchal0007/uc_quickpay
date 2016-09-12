<?php

namespace Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uc_credit\CreditCardPaymentMethodBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;

/**
 * QuickPay Ubercart gateway payment method.
 *
 *
 * @UbercartPaymentMethod(
 *   id = "quickpay_gateway",
 *   name = @Translation("QuickPay"),
 * )
 */
class QuickPayGateway extends PaymentMethodPluginBase {
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
        $elements = ['merchant_id', 'private_key', 'agreement_id', 'api_key'];

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
        foreach (['merchant_id', 'private_key', 'agreement_id', 'api_key'] as $item) {
            $this->configuration['api'][$item] = $form_state->getValue(['settings', 'api', $item]);
        }
        $this->configuration['language'] = $form_state->getValue('language');
        $this->configuration['currency'] = $form_state->getValue('currency');
        $this->configuration['callbacks']['continue_url'] = $form_state->getValue(['settings', 'callbacks', 'continue_url']);
        $this->configuration['callbacks']['cancel_url'] = $form_state->getValue(['settings', 'callbacks', 'cancel_url']);

        return parent::submitConfigurationForm($form, $form_state);
    }

    public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state) {
    
        $params = array(
            "version"      => QUICKPAY_VERSION,
            "merchant_id"  => $this->configuration['api']['merchant_id'],
            "agreement_id" => $this->configuration['api']['agreement_id'],
            "order_id"     => $order->id(),
            "amount"       => $order->getTotal(),
            "currency"     => $this->configuration['currency'],
            "continueurl" => $this->configuration['callbacks']['continue_url'],
            "cancelurl"   => $this->configuration['callbacks']['cancel_url'],
        );

        $params["checksum"] = $this->getChecksum($params, $this->configuration['api_key']);
        
        if (!empty($params["checksum"])) {
            \Drupal::service('user.private_tempstore')->get('uc_quickpay')->set('uc_quickpay_token', $params["checksum"]);
        }
        return parent::cartProcess($order, $form, $form_state); // TODO: Change the autogenerated stub
    }


    // /**
    // * {@inheritdoc}
    // */
    // // review order process
    // protected function chargeCard(OrderInterface $order, $amount, $txn_type, $reference = NULL) {
    //     $payment_method_names = \Drupal::configFactory()->listAll('uc_payment.method');
    //     $payment_method = '';

    //     foreach ($payment_method_names as $method) {
    //         $config = \Drupal::config($method);
    //         $data = $config->get();
    //         $payment_method = $data['plugin'];
    //     }

    //     if($payment_method == "paytrek_gateway"){
            
    //         $user = \Drupal::currentUser();
        
    //         $amount = uc_currency_format($amount, FALSE, FALSE, FALSE);

    //         $data = array();
    //         // Required variables.
    //         $data['version'] = QUICKPAY_VERSION;
    //         $data['merchant_id'] = $this->configuration['merchant_id'];
    //         $data['agreement_id'] = $this->configuration['agreement_id'];
    //         $data['order_id'] = $order->id();

    //         $data['amount'] = $amount;
    //         $data['currency'] = $this->configuration['currency'];
    //         $data['continueurl'] = $this->configuration['continue_url'];
    //         $data['cancelurl'] = $this->configuration['cancel_url'];
    //         // End of required variables.
    //         //$data['callbackurl'] = url(quickpay_hash_path('quickpay/' . $order_id . '/' . $module), array('absolute' => TRUE));

    //         $data['language'] = isset($this->configuration['currency']) ? $this->configuration['currency'] : '';

    //         $data['autocapture'] = ($options['autocapture']) ? '1' : '0';
    //         $data['checksum'] = $this->getChecksum($data);

    //     }
        
    //     // if (!$this->prepareApi()) {
    //     //     $result = array(
    //     //     'success' => FALSE,
    //     //     'comment' => t('Paytrek API not found.'),
    //     //     'message' => t('Paytrek API not found. Contact the site administrator.'),
    //     //     'uid' => $user->id(),
    //     //     'order_id' => $order->id(),
    //     //     );
    //     //     return $result;
    //     // }

    //     // Format the amount in cents, which is what Paytrek wants
        

    //     //$Paytrek_customer_id = FALSE;

    //     // If the user running the order is not the order's owner
    //     // (like if an admin is processing an order on someone's behalf)
    //     // then load the customer ID from the user object.
    //     // Otherwise, make a brand new customer each time a user checks out.
    //     // if ($user->id() != $order->getOwnerId()) {
    //     //     $Paytrek_customer_id = $this->getPaytrekCustomerID($order->id());
    //     // }


    // // Always Create a new customer in Paytrek for new orders

    //     // if (!$Paytrek_customer_id) {

    //     //   try {
    //     //     // If the token is not in the user's session, we can't set up a new customer
    //     //     $Paytrek_token = \Drupal::service('user.private_tempstore')->get('uc_Paytrek')->get('uc_Paytrek_token');

    //     //     if (empty($Paytrek_token)) {
    //     //       throw new \Exception('Token not found');
    //     //     }

    //     //     //Create the customer in Paytrek
    //     //     $customer = \Paytrek\Customer::create(array(
    //     //         "source" => $Paytrek_token,
    //     //         'description' => "OrderID: {$order->id()}",
    //     //         'email' => $order->getEmail(),
    //     //       )
    //     //     );

    //     //     // Store the customer ID in temp storage,
    //     //     // We'll pick it up later to save it in the database since we might not have a $user object at this point anyway
    //     //     \Drupal::service('user.private_tempstore')->get('uc_Paytrek')->set('uc_Paytrek_customer_id', $customer->id);

    //     //   } catch (Exception $e) {
    //     //     $result = array(
    //     //       'success' => FALSE,
    //     //       'comment' => $e->getCode(),
    //     //       'message' => t("Paytrek Customer Creation Failed for order !order: !message", array(
    //     //         "!order" => $order->id(),
    //     //         "!message" => $e->getMessage()
    //     //       )),
    //     //       'uid' => $user->id(),
    //     //       'order_id' => $order->id(),
    //     //     );

    //     //     uc_order_comment_save($order->id(), $user->id(), $result['message']);

    //     //     \Drupal::logger('uc_Paytrek')
    //     //       ->notice('Failed Paytrek customer creation: @message', array('@message' => $result['message']));
    //     //     $message = $this->t('Credit card charge failed.');
    //     //     uc_order_comment_save($order->id(), $user->id(), $message, 'admin');
    //     //     return $result;
    //     //   }

    //     //   $message = $this->t('Credit card charged: @amount', ['@amount' => uc_currency_format($amount)]);
    //     //   uc_order_comment_save($order->id(), $user->id(), $message, 'admin');

    //     //   $result = array(
    //     //     'success' => TRUE,
    //     //     'comment' => $this->t('Card charged, resolution code: 0022548315'),
    //     //     'message' => $this->t('Credit card payment processed successfully.'),
    //     //     'uid' => $user->id(),
    //     //   );

    //     //   return $result;
    //     // }
    // }


    /**
      * Utility function: Load Paytrek API
      *
      * @return bool
    */
    public function prepareApi() {
        // Not clear that this is useful since payment config form forces at least some config
        if (!_uc_quickpay_check_api_keys($this->getConfiguration())) {
            \Drupal::logger('uc_paytrek')->error('Paytrek API keys are not configured. Payments cannot be made without them.', array());
            return FALSE;
        }

        $private_key = $this->configuration['merchant_id'];
        // try {
        //     \Paytrek\Paytrek::setApiKey($private_key);
        // } catch (Exception $e) {
        //     \Drupal::logger('uc_Paytrek')->notice('Error setting the Paytrek API Key. Payments will not be processed: %error', array('%error' => $e->getMessage()));
        // }
        return TRUE;
    }

    // function review_form_with_quickpay(OrderInterface $order, array $form, FormStateInterface $form_state){
    //     var_dump('test');
    //     exit;
    //     $form['quickpay_token'] = array(
    //         '#type' => 'hidden',
    //         '#default_value' => 'default',
    //         '#attributes' => array(
    //             'id' => 'edit-panes-payment-details-quickpay-token',
    //         ),
    //     );   
    //     return $form;
    // }

  // /**
  //  * @param string $number
  //  * @return bool
  //  */
  // protected function validateCardNumber($number) {
  //   // Do nothing - let Paytrek validate the number
  //   return TRUE;
  // }

  // /**
  //  * @param string $cvv
  //  * @return bool
  //  */
  // protected function validateCvv($cvv) {
  //   // Do nothing - let Paytrek validate the CVV
  //   return TRUE;
  // }

  // /**
  //  * Retrieve the Paytrek customer id for a user
  //  *
  //  * @param $uid
  //  * @return string|NULL
  //  */
  // function getPaytrekCustomerID($uid) {

  //   /** @var \Drupal\user\UserDataInterface $userdata_container */
  //   $userdata_container = \Drupal::getContainer('user.data');

  //   $id = $userdata_container->get('uc_Paytrek', $uid, 'uc_Paytrek_customer_id');
  //   return $id;
  // }
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