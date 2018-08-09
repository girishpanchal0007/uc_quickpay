<?php

namespace Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uc_credit\CreditCardPaymentMethodBase;
use Drupal\uc_order\OrderInterface;
use Drupal\Component\Utility\Html;
use QuickPay\QuickPay;

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
      UC_CREDIT_REFERENCE_TXN,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel($label) {
    $form['label'] = [
      '#prefix' => '<div class="uc-quickpay uc-quickpay-embedded">',
      '#plain_text' => $label,
      '#suffix' => '</div>',
    ];
    $cc_types = $this->getEnabledTypes();
    foreach ($cc_types as $type => $description) {
      $form['image'][$type] = [
        '#theme' => 'image',
        '#uri' => drupal_get_path('module', 'uc_quickpay') . '/images/' . $type . '.gif',
        '#alt' => $description,
        '#attributes' => ['class' => ['uc-quickpay-embedded', 'uc-quickpay-cctype-' . $type]],
      ];
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
        'pre_order_id'    => '',
      ],
      'callbacks' => [
        'continue_url'    => '',
        'cancel_url'      => '',
      ],
      '3d_secure'         => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API credentials'),
      '#description' => $this->t('@link for information on obtaining credentials. You need to acquire an API Signature. If you have already logged-in your quickpay, you can review your settings under the Integration section of your QuickPayGateway profile.', ['@link' => Link::fromTextAndUrl($this->t('Click here'), Url::fromUri('https://manage.quickpay.net/'))->toString()]),
      '#open' => TRUE,
    ];
    $form['api']['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#default_value' => $this->configuration['api']['merchant_id'],
      '#description' => $this->t('This is your Merchant Account id.'),
      '#required' => TRUE,
    ];
    $form['api']['user_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API user key'),
      '#default_value' => $this->configuration['api']['user_api_key'],
      '#description' => $this->t('This is an API user key.'),
      '#required' => TRUE,
    ];
    $form['api']['agreement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agreement ID'),
      '#default_value' => $this->configuration['api']['agreement_id'],
      '#description' => $this->t('This is Payment Window Agreement id.'),
      '#required' => TRUE,
    ];
    $form['api']['payment_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->configuration['api']['payment_api_key'],
      '#description' => $this->t('This is Payment Window API key.'),
      '#required' => TRUE,
    ];
    $form['api']['pre_order_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order id prefix'),
      '#default_value' => $this->configuration['api']['pre_order_id'],
      '#description' => $this->t('Prefix for order ids. Order ids must be uniqe when sent to QuickPay, use this to resolve clashes.'),
      '#required' => TRUE,
    ];
    $form['callbacks'] = [
      '#type' => 'details',
      '#title' => $this->t('CALLBACKS'),
      '#description' => $this->t('Quickpay callback urls.'),
      '#open' => TRUE,
    ];
    $form['callbacks']['continue_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Continue URL'),
      '#default_value' => $this->configuration['callbacks']['continue_url'],
      '#description' => $this->t('The customer will be redirected to this URL upon a successful payment. No data will be send to this URL.'),
    ];
    $form['callbacks']['cancel_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cancel URL'),
      '#default_value' => $this->configuration['callbacks']['cancel_url'],
      '#description' => $this->t('The customer will be redirected to this URL if the customer cancels the payment. No data will be send to this URL.'),
    ];
    $form['3d_secure'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('3D Secure Creditcard'),
      '#description' => $this->t('Checked 3D Secure Creditcard if you wish to make payment under 3D secure.'),
      '#default_value' => $this->configuration['3d_secure'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Numeric validation for all the id's.
    $element_ids = [
      'merchant_id',
      'agreement_id',
      'pre_order_id',
    ];
    foreach ($element_ids as $element_id) {
      $raw_key = $form_state->getValue(['settings', 'api', $element_id]);
      if (!is_numeric($raw_key)) {
        $form_state->setError($element_ids, $this->t('The @name @value is not valid. It must be numeric',
          [
            '@name' => $element_id,
            '@value' => $raw_key,
          ]
        ));
      }
    }
    // Key's validation.
    $element_keys = [
      'user_api_key',
      'payment_api_key',
    ];
    foreach ($element_keys as $element_name) {
      $raw_key = $form_state->getValue(['settings', 'api', $element_name]);
      $sanitized_key = $this->trimKey($raw_key);
      $form_state->setValue(['settings', $element_name], $sanitized_key);
      if (!$this->validateKey($form_state->getValue(['settings', $element_name]))) {
        $form_state->setError($element_keys, $this->t('@name does not appear to be a valid QuickPay key',
          [
            '@name' => $element_name,
          ]
        ));
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
  public function validateKey($key) {
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
    $form["cc_number"]['#attributes'] = ['data-quickpay' => 'cardnumber', 'placeholder' => '**** **** **** ****'];
    $form["cc_number"]['#weight'] = 1;
    // Unset month and year.
    unset($form['cc_exp_month']);
    unset($form['cc_exp_year']);
    // New field for data like MM / YY.
    $form['date_year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Expiration date'),
      '#default_value' => '',
      '#attributes' => [
        'autocomplete' => 'off',
        'data-quickpay' => 'expiration',
        'id' => 'cc-date-year',
        'placeholder' => 'MM / YY',
      ],
      '#size' => 20,
      '#maxlength' => 19,
      '#weight' => 2,
    ];
    // Placeholder.
    $form["cc_cvv"]['#attributes'] = ['data-quickpay' => 'cvd', 'placeholder' => '***'];
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
    if (substr($cc_data['cc_number'], 0, strlen($this->t('(Last4)'))) == $this->t('(Last4)')) {
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
      $review[] = ['title' => $this->t('Card type'), 'data' => $order->payment_details['cc_type']];
    }
    if (!empty($fields['owner'])) {
      $review[] = ['title' => $this->t('Card owner'), 'data' => $order->payment_details['cc_owner']];
    }
    $review[] = ['title' => $this->t('Card number'), 'data' => $this->displayCardNumber($order->payment_details['cc_number'])];
    $review[] = ['title' => $this->t('Expiration'), 'data' => $order->payment_details['date_year']];
    return $review;
  }

  /**
   * {@inheritdoc}
   */
  public function orderView(OrderInterface $order) {
    $build = [];
    // Add the hidden span for the CC details if possible.
    $payment_id = db_query("SELECT payment_id FROM {uc_payment_quickpay_callback} WHERE order_id = :id ORDER BY created_at ASC", [':id' => $order->id()])->fetchField();
    $account = \Drupal::currentUser();
    if ($account->hasPermission('view cc details')) {
      $rows = [];
      if (!empty($order->payment_details['cc_type'])) {
        $rows[] = $this->t('Card type:') . $order->payment_details['cc_type'];
      }
      if (!empty($order->payment_details['cc_number'])) {
        $rows[] = $this->t('Card number:') . $this->displayCardNumber($order->payment_details['cc_number']);
      }
      if (!empty($order->payment_details['cc_exp_month']) && !empty($order->payment_details['cc_exp_year'])) {
        $rows[] = $this->t('Expiration:') . $order->payment_details['date_year'];
      }
      if (empty($payment_id)) {
        $rows[] = $this->t('Payment ID: @payment_id', ['@payment_id' => 'Unknown']);
      }
      else {
        $rows[] = $this->t('Payment ID: @payment_id', ['@payment_id' => $payment_id]);
      }
      $build['cc_info'] = [
        '#markup' => implode('<br />', $rows) . '<br />',
      ];
    }
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
      uc_payment_enter($order->id(), $this->getPluginId(), $amount,
        empty($result['uid']) ? 0 : $result['uid'],
        empty($result['message']) ? '' : $result['message'],
        empty($result['comment']) ? '' : $result['comment']
      );
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
      $result = [
        'success' => FALSE,
        'comment' => $this->t('QuickPay API not found.'),
        'message' => $this->t('QuickPay API not found. Contact the site administrator.'),
        'uid' => $order->getOwnerId(),
        'order_id' => $order->id(),
      ];
      return $result;
    }
    // Product Detail.
    $productData = [];
    foreach ($order->products as $item) {
      $productData = [
        'qty' => $item->qty->value,
        'item_no' => $item->model->value,
        'item_name' => $item->title->value,
        'item_price' => uc_currency_format($item->price->value, FALSE, FALSE, '.'),
        'vat_rate' => 1,
      ];
    }
    $amount_currency = uc_currency_format($amount, FALSE, FALSE, FALSE);
    $country = $country = \Drupal::service('country_manager')->getCountry($order->getAddress('billing')->country)->getAlpha3();
    $card_token = \Drupal::service('user.private_tempstore')->get('uc_quickpay')->get('card_token');
    // Create payment to get payment_id.
    $paymentform = [
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
    ];
    $payments = $this->payClient()->request->post('/payments', $paymentform);
    // Return Response.
    $payment = $payments->asObject();
    $status = $payments->httpStatus();
    // Checking status code.
    if ($status == 201) {
      // Authorise the payment.
      $paymentdata = [
        'amount' => $amount_currency,
        'card'   => [
          'token' => $card_token,
          'status' => isset($this->configuration['3d_secure']) ? "true" : "false",
        ],
        // 'auto_capture' => false.
        // 'test_mode' => isset($this->configuration['testmode']) ? 1 : 0.
        // 'acquirer' => 'clearhaus'.
      ];
      $authorize_obj = $this->payClient()->request->post("/payments/{$payment->id}/authorize?synchronized", $paymentdata);
      $authorize_data = $authorize_obj->asObject();
      // Checking success response.
      if ($authorize_obj->isSuccess()) {
        // To capture payment using capture class below.
        $payment_capture = $this->capture($order, $payment->id, $amount_currency);
        $message = $this->t('QuickPay credit card payment was successfully: @amount.', ['@amount' => uc_currency_format($amount)]);
        uc_order_comment_save($order->id(), $order->getOwnerId(), $message, 'admin');
        // Get string length.
        $order_length = strlen((string) $order->id());
        $orderID = substr($payment_capture->order_id, -$order_length);
        // Update callback in database.
        db_insert('uc_payment_quickpay_callback')
          ->fields([
            'order_id' => $orderID,
            'payment_id' => $payment_capture->id,
            'merchant_id' => $payment_capture->merchant_id,
            'payment_type' => $payment_capture->metadata->type,
            'payment_brand' => $payment_capture->metadata->brand,
            'payment_amount' => $payment_capture->operations[0]->amount,
            'payment_status' => $payment_capture->operations[0]->qp_status_msg,
            'customer_email' => $payment_capture->invoice_address->email,
            'created_at' => REQUEST_TIME,
          ])
          ->execute();
        // Store result.
        $result = [
          'success' => TRUE,
          'comment' => $this->t('Payment charged,'),
          'message' => $this->t('QuickPay credit card payment was successfully.'),
          'uid' => $order->getOwnerId(),
        ];
        // Return result.
        return $result;
      }
      else {
        // Store result.
        $result = [
          'success' => FALSE,
          'comment' => $this->t("Payment authorize is failed"),
          'message' => $this->t("QuickPay credit card payment is not authorize for order @order:", ['@order' => $order->id()]),
          'uid' => $order->getOwnerId(),
        ];
        \Drupal::logger('uc_quickpay')->notice($authorize_data->message);
        // Order comment.
        uc_order_comment_save($order->id(), $order->getOwnerId(), $authorize_data->message, 'admin');
        // Return result.
        return $result;
      }
    }
    else {
      // Error for payment->message.
      drupal_set_message($payment->message, 'error', FALSE);
      // Order comment.
      uc_order_comment_save($order->id(), $order->getOwnerId(), $payment->message, 'admin');
      // Store result.
      $result = [
        'success' => FALSE,
        'comment' => $this->t("QuickPay payments not authorized"),
        'message' => $this->t("QuickPay payment is not authorized for order @order. Please try again with new order id.", ['@order' => $order->id()]),
        'uid' => $order->getOwnerId(),
      ];
      return $result;
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
    $capturedata = [
      'amount' => $amount,
    ];
    $capture_res = $this->captureClient()->request->post("/payments/{$payment_id}/capture?synchronized", $capturedata);
    // Response of capture payment.
    $capture_data = $capture_res->asObject();
    // Cheking response sucees.
    if (!$capture_res->isSuccess()) {
      \Drupal::logger('uc_quickpay')->notice($capture_data->message);
      // Order comment.
      uc_order_comment_save($order->id(), $order->getOwnerId(), $capture_data->message, 'admin');
      // Result store.
      $result = [
        'success' => FALSE,
        'comment' => $this->t("Payment capture is failed"),
        'message' => $this->t("QuickPay credit card payment is not capture for order @order: @message", [
          '@order' => $order->id(),
          '@message' => $capture_data->message,
        ]),
        'uid' => $order->getOwnerId(),
      ];
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
    // Checking API keys configuration.
    if (!uc_quickpay_check_api_keys_and_ids($this->getConfiguration())) {
      \Drupal::logger('uc_quickpay')->error('QuickPay API keys are not configured. Payments cannot be made without them.', []);
      return FALSE;
    }
    return TRUE;
  }

}
