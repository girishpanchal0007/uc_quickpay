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
 * @UbercartPaymentMethod(
 *   id = "quickpay_gateway",
 *   name = @Translation("QuickPay Embedded"),
 *   label = @Translation("QuickPay Embedded"),
 * )
 */
class QuickPayGateway extends CreditCardPaymentMethodBase {

  /**
   * Returns the set of fields which are used by this payment method.
   *
   * @return array
   *   An array with keys 'cvv', 'owner', 'start', 'issue', 'bank' and 'type'.
   */
  public function getEnabledFields() {
    return [
      'cvv' => TRUE,
      'owner' => FALSE,
      'start' => FALSE,
      'issue' => FALSE,
      'type' => FALSE,
    ];
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
      'maestro'    => $this->t('Maestro'),
      'visa'       => $this->t('Visa'),
      'mastercard' => $this->t('MasterCard'),
      'amex'       => $this->t('American Express'),
      'dankort'    => $this->t('Dankort'),
      'diners'     => $this->t('Diners'),
    ];
  }

  /**
   * Returns the set of transaction types allowed by this payment method.
   *
   * @return array
   *   An array with values UC_CREDIT_AUTH_ONLY, UC_CREDIT_PRIOR_AUTH_CAPTURE,
   *   UC_CREDIT_AUTH_CAPTURE, UC_CREDIT_REFERENCE_SET, UC_CREDIT_REFERENCE_TXN,
   *   UC_CREDIT_REFERENCE_REMOVE, UC_CREDIT_REFERENCE_CREDIT, UC_CREDIT_CREDIT
   *   and UC_CREDIT_VOID.
   */
  public function getTransactionTypes() {
    return [
      UC_CREDIT_AUTH_CAPTURE,
      UC_CREDIT_AUTH_ONLY,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel($label) {
    $form['label'] = array(
      '#prefix' => ' ',
      '#plain_text' => $label,
    );
    $cc_types = $this->getEnabledTypes();
    foreach ($cc_types as $type => $description) {
      $form['image'][$type] = array(
        '#theme' => 'image',
        '#uri' => drupal_get_path('module', 'uc_quickpay') . '/images/' . $type . '.gif',
        '#alt' => $description,
        '#attributes' => array('class' => array('uc-quickpay-cctype', 'uc-quickpay-cctype-' . $type)),
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'api' => [
        'merchant_id'     => '',
        'user_api_key'    => '',
        'agreement_id'    => '',
        'payment_api_key' => '',
        'language'        => '',
        'currency'        => '',
      ],
      'callbacks' => [
        'continue_url'    => '',
        'cancel_url'      => '',
      ],
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
      '#title' => $this->t('Merchant ID'),
      '#default_value' => $this->configuration['api']['merchant_id'],
      '#description' => $this->t('The Merchant ID as shown in the QuickPay admin.'),
    );
    $form['api']['user_api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API user key'),
      '#default_value' => $this->configuration['api']['user_api_key'],
      '#description' => $this->t('This is an API user key.'),
    );
    $form['api']['agreement_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Agreement ID'),
      '#default_value' => $this->configuration['api']['agreement_id'],
      '#description' => $this->t('This is a payment window agreement ID.'),
    );
    $form['api']['payment_api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payment Window API key'),
      '#default_value' => $this->configuration['api']['payment_api_key'],
      '#description' => $this->t('This is a payment window API key.'),
    );
    $form['api']['pre_order_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Order id prefix'),
      '#default_value' => $this->configuration['api']['pre_order_id'],
      '#description' => $this->t('Prefix for order ids. Order ids must be uniqe when sent to QuickPay, use this to resolve clashes.'),
    );
    $form['3d_secure'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('3D Secure Creditcard'),
      '#description' => $this->t('Checked 3D Secure Creditcard if you wish to make payment under 3D secure.'),
      '#default_value' => $this->configuration['3d_secure'],
    );
    $form['callbacks'] = array(
      '#type' => 'details',
      '#title' => $this->t('CALLBACKS'),
      '#description' => $this->t('Quickpay callback urls.'),
      '#open' => TRUE,
    );
    $form['callbacks']['continue_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Continue URL'),
      '#default_value' => $this->configuration['callbacks']['continue_url'],
      '#description' => $this->t('After a successful transaction.'),
    );
    $form['callbacks']['cancel_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Cancel URL'),
      '#default_value' => $this->configuration['callbacks']['cancel_url'],
      '#description' => $this->t('If the user cancels the QuickPay transaction.'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $elements = [
      'merchant_id',
      'user_api_key',
      'agreement_id',
      'payment_api_key',
      'pre_order_id',
    ];
    foreach ($elements as $element_name) {
      $raw_key = $form_state->getValue(['settings', 'api', $element_name]);
      $sanitized_key = $this->trimKey($raw_key);
      $form_state->setValue(['settings', $element_name], $sanitized_key);
      if (!$this->validateKey($form_state->getValue(['settings', $element_name]))) {
        $form_state->setError($form[$element_name], $this->t('@name does not appear to be a valid QuickPay key', array('@name' => $element_name)));
      }
    }
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * Checking vaildation keys of payment gateway.
   */
  protected function trimKey($key) {
    $key = trim($key);
    $key = Html::escape($key);
    return $key;
  }

  /**
   * Validate QuickPay key.
   *
   * @var $key
   *   Key which passing on admin side.
   *
   * @return bool
   *   Return that is key is vaild or not.
   */
  static public function validateKey($key) {
    $valid = preg_match('/^[a-zA-Z0-9_]+$/', $key);
    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $elements = [
      'merchant_id',
      'user_api_key',
      'agreement_id',
      'payment_api_key',
      'pre_order_id',
    ];
    foreach ($elements as $item) {
      $this->configuration['api'][$item] = $form_state->getValue([
        'settings',
        'api',
        $item,
      ]);
    }
    $this->configuration['3d_secure'] = $form_state->getValue('3d_secure');
    $this->configuration['callbacks']['continue_url'] = $form_state->getValue([
      'settings',
      'callbacks',
      'continue_url',
    ]);
    $this->configuration['callbacks']['cancel_url'] = $form_state->getValue([
      'settings',
      'callbacks',
      'cancel_url',
    ]);
    return parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function cartDetails(OrderInterface $order, array $form, FormStateInterface $form_state) {
    $form = parent::cartDetails($order, $form, $form_state);
    // Send merchant and agreement ids to JS.
    $form['#attached']['drupalSettings']['uc_quickpay']['merchant_id'] = $this->configuration['api']['merchant_id'];
    $form['#attached']['drupalSettings']['uc_quickpay']['agreement_id'] = $this->configuration['api']['agreement_id'];
    $form["cc_number"]['#attributes'] = array('data-quickpay' => 'cardnumber', 'placeholder' => '**** **** **** ****');
    $form["cc_number"]['#weight'] = 1;
    // Unset month and year.
    unset($form['cc_exp_month']);
    unset($form['cc_exp_year']);
    // New field for data like MM / YY.
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
      '#size' => 20,
      '#maxlength' => 19,
      '#weight' => 2,
    );
    // Placeholder.
    $form["cc_cvv"]['#attributes'] = array('data-quickpay' => 'cvd', 'placeholder' => '***');
    $form["cc_cvv"]['#weight'] = 3;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state) {
    if (!$form_state->hasValue(['panes', 'payment', 'details', 'cc_number'])) {
      return;
    }
    // Fetch the CC details from the $_POST directly.
    $cc_data = $form_state->getValue(['panes', 'payment', 'details']);
    $cc_data['cc_number'] = str_replace(' ', '', $cc_data['cc_number']);
    // Recover cached CC data in form state, if it exists.
    if (isset($cc_data['payment_details_data'])) {
      $cache = uc_credit_cache(base64_decode($cc_data['payment_details_data']));
      unset($cc_data['payment_details_data']);
    }
    // Account for partial CC numbers when masked by the system.
    if (substr($cc_data['cc_number'], 0, strlen($this->t(('(Last4)')))) == $this->t('(Last4)')) {
      // Recover the number from the encrypted data in the form if truncated.
      if (isset($cache['cc_number'])) {
        $cc_data['cc_number'] = $cache['cc_number'];
      }
      else {
        $cc_data['cc_number'] = '';
      }
    }
    // Go ahead and put the CC data in the payment details array.
    $order->payment_details = $cc_data;
    // Initialize the encryption key and class.
    $key = uc_credit_encryption_key();
    $crypt = \Drupal::service('uc_store.encryption');
    // Store the encrypted details in the session for the next pageload.
    // We are using base64_encode() because the encrypt function works with a
    // limited set of characters, not supporting the full Unicode character
    // set or even extended ASCII characters that may be present.
    // base64_encode() converts everything to a subset of ASCII, ensuring that
    // the encryption algorithm does not mangle names.
    $session = \Drupal::service('session');
    $session->set('sescrd', $crypt->encrypt($key, base64_encode(serialize($order->payment_details))));
    // Log any errors to the watchdog.
    uc_store_encryption_errors($crypt, 'uc_credit');
    if (isset($_POST['card_token'])) {
      $quickpay_card_token = $_POST['card_token'];
      if (!empty($quickpay_card_token)) {
        \Drupal::service('user.private_tempstore')->get('uc_quickpay')->set('card_token', $quickpay_card_token);
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function cartReviewTitle() {
    return $this->t('QuickPay Credit Card');
  }

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

  /**
   * {@inheritdoc}
   */
  public function orderView(OrderInterface $order) {
    $build = array();
    // Add the hidden span for the CC details if possible.
    $payment_id = db_query("SELECT payment_id FROM {uc_payment_quickpay_callback} WHERE order_id = :id ORDER BY created_at ASC", [':id' => $order->id()])->fetchField();
    $account = \Drupal::currentUser();
    if ($account->hasPermission('view cc details')) {
      $rows = array();
      if (!empty($order->payment_details['cc_type'])) {
        $rows[] = $this->t('Card type') . ': ' . $order->payment_details['cc_type'];
      }
      if (!empty($order->payment_details['cc_number'])) {
        $rows[] = $this->t('Card number') . ': ' . $this->displayCardNumber($order->payment_details['cc_number']);
      }
      if (!empty($order->payment_details['cc_exp_month']) && !empty($order->payment_details['cc_exp_year'])) {
        $rows[] = $this->t('Expiration') . ': ' . $order->payment_details['date_year'];
      }
      if (empty($payment_id)) {
        $rows[] = $this->t('Payment ID: @payment_id', ['@payment_id' => 'Unknown']);
      }
      else {
        $rows[] = $this->t('Payment ID: @payment_id', ['@payment_id' => $payment_id]);
      }
      $build['cc_info'] = array(
        '#markup' => implode('<br />', $rows) . '<br />',
      );
    }
    // Add the form to process the card if applicable.
    /*if ($account->hasPermission('process credit cards')) {
      $build['terminal'] = [
        '#type' => 'link',
        '#title' => $this->t('Process card'),
        '#url' => Url::fromRoute('uc_credit.terminal', [
          'uc_order' => $order->id(),
          'uc_payment_method' => $order->getPaymentMethodId(),
        ]),
      ];
    }*/
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function processPayment(OrderInterface $order, $amount, $txn_type, $reference = NULL) {
    // Ensure the cached details are loaded.
    // @todo Figure out which parts of this call are strictly necessary.
    $this->orderLoad($order);
    // Calling chargeCard.
    $result = $this->chargeCard($order, $amount, $txn_type, $reference);
    // If the payment processed successfully.
    if ($result['success'] === TRUE) {
      // Log the payment to the order if not disabled.
      if (!isset($result['log_payment']) || $result['log_payment'] !== FALSE) {
        uc_payment_enter($order->id(), $this->getPluginDefinition()['id'], $amount,
          empty($result['uid']) ? 0 : $result['uid'],
          empty($result['data']) ? '' : $result['data'],
          empty($result['comment']) ? '' : $result['comment']
        );
      }
    }
    else {
      // Otherwise display the failure message in the logs.
      \Drupal::logger('uc_payment')->warning('Payment failed for order @order_id: @message',
        [
          '@order_id' => $order->id(),
          '@message' => $result['message'],
          'link' => $order->toLink($this->t('view order'))->toString(),
        ]
      );
    }
    return $result['success'];
  }

  /**
   * {@inheritdoc}
   */
  protected function chargeCard(OrderInterface $order, $amount, $txn_type, $reference = NULL) {
    if (!$this->prepareApi()) {
      $result = array(
        'success' => FALSE,
        'comment' => $this->t('QuickPay API not found.'),
        'message' => $this->t('QuickPay API not found. Contact the site administrator.'),
        'uid' => $order->getOwnerId(),
        'order_id' => $order->id(),
      );
      return $result;
    }
    // Product Detail.
    $productData = array();
    foreach ($order->products as $item) {
      $productData = array(
        'qty' => $item->qty->value,
        'item_no' => $item->model->value,
        'item_name' => $item->title->value,
        'item_price' => uc_currency_format($item->price->value, FALSE, FALSE, '.'),
        'vat_rate' => 1,
      );
    }
    $amount_currency = uc_currency_format($amount, FALSE, FALSE, FALSE);
    $country = $country = \Drupal::service('country_manager')->getCountry($order->getAddress('billing')->country)->getAlpha3();
    $card_token = \Drupal::service('user.private_tempstore')->get('uc_quickpay')->get('card_token');
    // Create payment to get payment_id.
    $paymentform = array(
      'currency' => $order->getCurrency(),
      'order_id' => $this->configuration['api']['pre_order_id'] . $order->id(),
      'invoice_address' => [
        'email'    => $order->getEmail(),
        'name'     => $order->getAddress('billing')->first_name . ' ' . $order->getAddress('billing')->last_name,
        'street'   => $order->getAddress('billing')->street1,
        'city'     => $order->getAddress('billing')->city,
        'zip_code' => $order->getAddress('billing')->postal_code,
        'region'   => $order->getAddress('billing')->zone,
        'country_code'  => $country,
        'phone_number'  => $order->getAddress('billing')->phone,
      ],
      'basket[]' => $productData,
    );
    $payments = $this->payClient()->request->post('/payments', $paymentform);
    // Return Response.
    $payment = $payments->asObject();
    $status = $payments->httpStatus();
    // Checking status code.
    if ($status == 201) {
      // Authorise the payment.
      $paymentdata = array(
        'amount' => $amount_currency,
        'card'   => [
          'token' => $card_token,
          'status' => isset($this->configuration['3d_secure']) ? "true" : "false",
        ],
        //'auto_capture' => false,
        //'test_mode' => isset($this->configuration['testmode']) ? 1 : 0,
        //'acquirer' => 'clearhaus',
      );
      $authorize_obj = $this->payClient()->request->post("/payments/{$payment->id}/authorize?synchronized", $paymentdata);
      $authorize_data = $authorize_obj->asObject();
      // Checking success response.
      if ($authorize_obj->isSuccess()) {
        // To capture payment using capture class below.
        $payment_capture = $this->capture($order, $payment->id, $amount_currency);
        $message = $this->t('QuickPay credit card payment was successfully: @amount', ['@amount' => uc_currency_format($amount)]);
        uc_order_comment_save($order->id(), $order->getOwnerId(), $message, 'admin');
        $orderID = strstr($payment_capture->order_id, $order->id());
        // Update callback in database.
        db_insert('uc_payment_quickpay_callback')
          ->fields(array(
            'order_id' => $orderID,
            'payment_id' => $payment_capture->id,
            'merchant_id' => $payment_capture->merchant_id,
            'payment_type' => $payment_capture->metadata->type,
            'payment_brand' => $payment_capture->metadata->brand,
            'payment_amount' => $payment_capture->operations[0]->amount,
            'payment_status' => $payment_capture->operations[0]->qp_status_msg,
            'customer_email' => $payment_capture->invoice_address->email,
            'created_at' => REQUEST_TIME,
          ))
          ->execute();
        // Store result.
        $result = array(
          'success' => TRUE,
          'comment' => $this->t('Payment charged,'),
          'message' => $this->t('QuickPay credit card payment was successfully.'),
          'uid' => $order->getOwnerId(),
        );
        // Return result.
        return $result;
      }
      else {
        // Store result.
        $result = array(
          'success' => FALSE,
          'comment' => $this->t("Payment authorize is failed"),
          'message' => $this->t("QuickPay credit card payment is not authorize for order !order:", array('!order' => $order->id())),
          'uid' => $order->getOwnerId(),
        );
        \Drupal::logger('uc_quickpay')->notice($authorize_data->message);
        // Order comment.
        uc_order_comment_save($order->id(), $order->getOwnerId(), $authorize_data->message, 'admin');
        // Return result.
        return $result;
      }
    }
    else {
      // Error for payment->message.
      drupal_set_message($this->t('QuickPay credit card payment creating.'), 'error', FALSE);
      \Drupal::logger('uc_quickpay')->notice($payment->message);
      // Order comment.
      uc_order_comment_save($order->id(), $order->getOwnerId(), $payment->message, 'admin');
      // Return result.
      return $result['success'] = FALSE;
    }
  }

  /**
   * Return Quickpay client.
   *
   * @return Quickpay
   *   The client.
   */
  public function payClient() {
    $payment_api_key = $this->configuration['api']['payment_api_key'];
    return new QuickPay(":{$payment_api_key}");
  }

  /**
   * Return Quickpay client.
   *
   * @return Quickpay
   *   The client.
   */
  public function captureClient() {
    $user_api_key = $this->configuration['api']['user_api_key'];
    return new QuickPay(":{$user_api_key}");
  }

  /**
   * Capture on an authorised payment.
   */
  public function capture($order, $payment_id, $amount) {
    // Capture payment.
    $capturedata = array(
      'amount' => $amount,
    );
    $capture_res = $this->captureClient()->request->post("/payments/{$payment_id}/capture?synchronized", $capturedata);
    // Response of capture payment.
    $capture_data = $capture_res->asObject();
    // Cheking response sucees.
    if (!$capture_res->isSuccess()) {
      \Drupal::logger('uc_quickpay')->notice($capture_data->message);
      // Order comment.
      uc_order_comment_save($order->id(), $order->getOwnerId(), $capture_data->message, 'admin');
      // Result store.
      $result = array(
        'success' => FALSE,
        'comment' => $this->t("Payment capture is failed"),
        'message' => $this->t("QuickPay credit card payment is not capture for order !order: !message", array(
          '!order' => $order->id(),
          '!message' => $capture_data->message,
        )),
        'uid' => $order->getOwnerId(),
      );
      // Return result.
      return $result;
    }
    return $capture_data;
  }

  /**
   * Utility function: Load QuickPay API.
   *
   * @return bool
   *   Checking PrepareApi is set or not.
   */
  public function prepareApi() {
    // Not clear that this is useful since payment config form forces at least some config.
    if (!_uc_quickpay_check_api_keys($this->getConfiguration())) {
      \Drupal::logger('uc_quickpay')->error('QuickPay API keys are not configured. Payments cannot be made without them.', array());
      return FALSE;
    }
    return TRUE;
  }

}
