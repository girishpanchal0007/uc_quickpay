<?php

namespace Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Utility\Html;
use Drupal\uc_payment\PaymentMethodPluginBase;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;

/**
 * QuickPay Ubercart gateway payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "quickpay_form_gateway",
 *   name = @Translation("QuickPay Form"),
 *   label = @Translation("QuickPay Form"),
 * )
 */
class QuickPayPaymentForm extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel($label) {
    $build['label'] = [
      '#prefix' => '<span class="uc-quickpay-form">',
      '#plain_text' => $label,
      '#suffix' => '</span>',
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api' => [
        'merchant_id'     => '',
        'private_key'     => '',
        'agreement_id'    => '',
        'payment_api_key' => '',
        'pre_order_id'    => '',
      ],
      'language'          => 'en',
      'payment_method'    => 'creditcard',
      'accepted_cards'    => '',
      'autofee'           => FALSE,
      'autocapture'       => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API credentials'),
      '#description' => $this->t('@link for obtaining information of quickpay credentials. You need to acquire an API Signature. If you have already logged in your Quickpay then you can review your settings under the integration section of your QuickPay Gateway profile. Quickpay Form Method must needed callback URL which you need to add setting under the integration e.g http://www.example.com/callback/', ['@link' => Link::fromTextAndUrl($this->t('Click here'), Url::fromUri('https://manage.quickpay.net/'))->toString()]),
      '#open' => TRUE,
    ];
    $form['api']['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#default_value' => $this->configuration['api']['merchant_id'],
      '#description' => $this->t('This is your Merchant Account id.'),
      '#required' => TRUE,
    ];
    $form['api']['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private Key'),
      '#default_value' => $this->configuration['api']['private_key'],
      '#description' => $this->t('This is your Merchant Private Key.'),
      '#required' => TRUE,
    ];
    $form['api']['agreement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agreement ID'),
      '#default_value' => $this->configuration['api']['agreement_id'],
      '#description' => $this->t('This is your Payment Window Agreement id. The checksum must be signed with the API-key belonging to this Agreement.'),
      '#required' => TRUE,
    ];
    $form['api']['payment_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->configuration['api']['payment_api_key'],
      '#description' => $this->t('This is your Payment Window API key.'),
      '#required' => TRUE,
    ];
    $form['api']['pre_order_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order id prefix'),
      '#default_value' => $this->configuration['api']['pre_order_id'],
      '#description' => $this->t('Prefix of order ids. Order ids must be uniqe when sent to QuickPay, Use this to resolve clashes.'),
      '#required' => TRUE,
    ];
    $form['language'] = [
      '#type' => 'select',
      '#options' => [
        'en' => $this->t('English'),
        'da' => $this->t('Danish'),
        'de' => $this->t('German'),
        'fr' => $this->t('French'),
        'it' => $this->t('Italian'),
        'no' => $this->t('Norwegian'),
        'nl' => $this->t('Dutch'),
        'pl' => $this->t('Polish'),
        'se' => $this->t('Swedish'),
      ],
      '#title' => $this->t('Payment Language'),
      '#default_value' => $this->configuration['language'],
      '#description' => $this->t('Set the language of the user interface. Defaults to English.'),
    ];
    $form['payment_method'] = [
      '#type' => 'radios',
      '#options' => [
        'creditcard' => $this->t('Creditcard'),
        '3d-creditcard' => $this->t('3D-Secure Creditcard'),
        'selected' => $this->t('Selected Payment Methods'),
      ],
      '#title' => $this->t('Accepted Payment Methods'),
      '#default_value' => $this->configuration['payment_method'],
      '#description' => $this->t('Which payment methods to accept. NOTE: Some require special agreements.'),
    ];

    $options = [];
    // Add card label for payment method.
    foreach ($this->getQuickpayCardTypes() as $key => $card) {
      $options[$key] = empty($card['image']) ? $card['name'] : '<img src="' . $card['image'] . '" rel="' . $key . '" /> ' . $card['name'];
    }
    $form['accepted_cards'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Select Accepted Cards'),
      '#default_value' => $this->configuration['accepted_cards'],
      '#states' => [
        'visible' => [
          ':input[name="settings[payment_method]"]' => ['value' => 'selected'],
        ],
      ],
    ];
    $form['autofee'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autofee'),
      '#default_value' => $this->configuration['autofee'],
      '#description' => $this->t('If set 1, the fee charged by the acquirer will be calculated and added to the transaction amount.'),
    ];
    $form['autocapture'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autocapture'),
      '#default_value' => $this->configuration['autocapture'],
      '#description' => $this->t('If set to 1, the payment will be captured automatically.'),
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
      'private_key',
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
      'private_key',
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
    $this->configuration['language'] = $form_state->getValue(['settings', 'language']);
    $this->configuration['payment_method'] = $form_state->getValue(['settings', 'payment_method']);
    $this->configuration['accepted_cards'] = $form_state->getValue(['settings', 'accepted_cards']);
    $this->configuration['autofee'] = $form_state->getValue(['settings', 'autofee']);
    $this->configuration['autocapture'] = $form_state->getValue(['settings', 'autocapture']);
    return parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function orderView(OrderInterface $order) {
    $payment_id = db_query("SELECT payment_id FROM {uc_payment_quickpay_callback} WHERE order_id = :id ORDER BY created_at ASC", [':id' => $order->id()])->fetchField();
    if (empty($payment_id)) {
      $payment_id = $this->t('Unknown');
    }
    $build['#markup'] = $this->t('Payment ID: @payment_id', ['@payment_id' => $payment_id]);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
    // Get billing address object.
    $bill_address = $order->getAddress('billing');
    $country = $country = \Drupal::service('country_manager')->getCountry($bill_address->country)->getAlpha3();
    // Formate current with multiply 100.
    $amount_currency = uc_currency_format($order->getTotal(), FALSE, FALSE, FALSE);
    $data = [];
    // Required parameter.
    $data['version'] = 'v10';
    $data['merchant_id'] = $this->configuration['api']['merchant_id'];
    $data['agreement_id'] = $this->configuration['api']['agreement_id'];
    $data['order_id'] = $this->configuration['api']['pre_order_id'] . $order->id();
    $data['amount'] = $amount_currency;
    $data['currency'] = $order->getCurrency();
    $data['continueurl'] = Url::fromRoute('uc_quickpay.qpf_complete', ['uc_order' => $order->id()], ['absolute' => TRUE])->toString();
    $data['cancelurl'] = Url::fromRoute('uc_quickpay.qpf_cancel', ['uc_order' => $order->id()], ['absolute' => TRUE])->toString();
    $data['callbackurl'] = Url::fromRoute('uc_quickpay.qpf_callback', [], ['absolute' => TRUE])->toString();
    $data['language'] = $this->configuration['language'];

    $data['autocapture'] = $this->configuration['autocapture'] ? 1 : 0;
    $data['payment_methods'] = $this->getSelectedPaymentMethod();
    $data['autofee'] = $this->configuration['autofee'] ? 1 : 0;
    // Use callback variable to verify order id.
    $data['variables[uc_order_id]'] = $order->id();
    $data['customer_email'] = $order->getEmail();
    // Invoice detail.
    if (!empty($bill_address->first_name)) {
      $data['invoice_address[name]'] = $bill_address->first_name . " " . $bill_address->last_name;
    }
    $data['invoice_address[att]'] = $bill_address->street1;

    if (!empty($bill_address->street2)) {
      $data['invoice_address[street]'] = $bill_address->street2;
    }
    $data['invoice_address[zip_code]'] = $bill_address->postal_code;

    if (!empty($bill_address->city)) {
      $data['invoice_address[city]'] = $bill_address->city;
    }
    $data['invoice_address[region]'] = $bill_address->zone;
    $data['invoice_address[country_code]'] = $country;

    if (!empty($bill_address->phone)) {
      $data['invoice_address[phone_number]'] = $bill_address->phone;
    }
    $data['invoice_address[email]'] = $order->getEmail();
    // Static variable for loop.
    $i = 0;
    foreach ($order->products as $item) {
      $data['basket[' . $i . '][qty]'] = $item->qty->value;
      $data['basket[' . $i . '][item_no]'] = $item->model->value;
      $data['basket[' . $i . '][item_name]'] = $item->title->value;
      $data['basket[' . $i . '][item_price]'] = uc_currency_format($item->price->value, FALSE, FALSE, '.');
      $data['basket[' . $i . '][vat_rate]'] = 0;
      $i++;
    }
    // Checksum.
    $data['checksum'] = $this->checksumCal($data, $this->configuration['api']['payment_api_key']);
    // Add hidden field with new form.
    foreach ($data as $name => $value) {
      if (isset($value) || !empty($value)) {
        $form[$name] = ['#type' => 'hidden', '#value' => $value];
      }
    }
    $form['#action'] = 'https://payment.quickpay.net';
    $form['actions'] = ['#type' => 'actions'];
    // Text alter.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('QuickPay Payment'),
      '#id' => 'quickpay-submit',
    ];
    return $form;
  }

  /**
   * Returns the set of card types which are used by this payment method.
   *
   * @return array
   *   An array with keys as needed by the chargeCard() method and values
   *   that can be displayed to the customer.
   */
  protected function getQuickpayCardTypes() {
    $image_path = base_path() . drupal_get_path('module', 'uc_quickpay') . '/images/';
    return [
      'dankort' => [
        'name' => $this->t('Dankort'),
        'image' => $image_path . 'dankort.gif',
      ],
      'maestro' => [
        'name' => $this->t('Maestro'),
        'image' => $image_path . 'maestro.gif',
      ],
      '3d-maestro' => [
        'name' => $this->t('Maestro'),
        'image' => $image_path . 'maestro.gif',
      ],
      '3d-maestro-dk' => [
        'name' => $this->t('Maestro, issued in Denmark'),
        'image' => $image_path . 'maestro.gif',
      ],
      'visa' => [
        'name' => $this->t('Visa'),
        'image' => $image_path . 'visa.gif',
      ],
      'visa-dk' => [
        'name' => $this->t('Visa, issued in Denmark'),
        'image' => $image_path . 'visa.gif',
      ],
      '3d-visa' => [
        'name' => $this->t('Visa, using 3D-Secure'),
      ],
      '3d-visa-dk' => [
        'name' => $this->t('Visa, issued in Denmark, using 3D-Secure'),
      ],
      'visa-electron' => [
        'name' => $this->t('Visa Electron'),
        'image' => $image_path . 'visaelectron.jpg',
      ],
      'visa-electron-dk' => [
        'name' => $this->t('Visa Electron, issued in Denmark'),
        'image' => $image_path . 'visaelectron.jpg',
      ],
      '3d-visa-electron' => [
        'name' => $this->t('Visa Electron, using 3D-Secure'),
      ],
      '3d-visa-electron-dk' => [
        'name' => $this->t('Visa Electron, issued in Denmark, using 3D-Secure'),
      ],
      'mastercard' => [
        'name' => $this->t('Mastercard'),
        'image' => $image_path . 'mastercard.gif',
      ],
      'mastercard-dk' => [
        'name' => $this->t('Mastercard, issued in Denmark'),
        'image' => $image_path . 'mastercard.gif',
      ],
      'mastercard-debet-dk' => [
        'name' => $this->t('Mastercard debet card, issued in Denmark'),
        'image' => $image_path . 'mastercard.gif',
      ],
      '3d-mastercard' => [
        'name' => $this->t('Mastercard, using 3D-Secure'),
      ],
      '3d-mastercard-dk' => [
        'name' => $this->t('Mastercard, issued in Denmark, using 3D-Secure'),
      ],
      '3d-mastercard-debet-dk' => [
        'name' => $this->t('Mastercard debet, issued in Denmark, using 3D-Secure'),
      ],
      'amex' => [
        'name' => $this->t('American Express'),
        'image' => $image_path . 'amex.gif',
      ],
      'amex-dk' => [
        'name' => $this->t('American Express, issued in Denmark'),
        'image' => $image_path . 'amex.gif',
      ],
      'diners' => [
        'name' => $this->t('Diners'),
        'image' => $image_path . 'diners.gif',
      ],
      'diners-dk' => [
        'name' => $this->t('Diners, issued in Denmark'),
        'image' => $image_path . 'diners.gif',
      ],
      'mobilepay' => [
        'name' => $this->t('Mobilepay'),
        'image' => $image_path . 'mobilepay.gif',
      ],
      'sofort' => [
        'name' => $this->t('Sofort'),
        'image' => $image_path . 'sofort.gif',
      ],
      'jcb' => [
        'name' => $this->t('JCB'),
        'image' => $image_path . 'jcb.gif',
      ],
      '3d-jcb' => [
        'name' => $this->t('JCB, using 3D-Secure'),
      ],
      'fbg1886' => [
        'name' => $this->t('Forbrugsforeningen'),
        'image' => $image_path . 'forbrugsforeningen.gif',
      ],
      'paypal' => [
        'name' => $this->t('PayPal'),
        'image' => $image_path . 'paypal.jpg',
      ],
      'viabill' => [
        'name' => $this->t('ViaBill'),
        'image' => $image_path . 'viabill.png',
      ],
    ];
  }

  /**
   * Utility function: Load QuickPay API.
   *
   * @return bool
   *   Checking prepareApi is set or not.
   */
  public function prepareApi() {
    // Checking API keys configuration.
    if (!_uc_quickpay_check_api_keys($this->getConfiguration())) {
      \Drupal::logger('uc_quickpay')->error('QuickPay API keys are not configured. Payments cannot be made without them.', []);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get selected payment method.
   *
   * @return array
   *   Return selected card for accepting payment.
   */
  protected function getSelectedPaymentMethod() {
    $configurations = $this->getConfiguration();

    if ($configurations['payment_method'] !== 'selected') {
      return $configurations['payment_method'];
    }
    // Filter out all cards not selected.
    $cards = array_filter($configurations['accepted_cards'], function ($is_selected) {
      return $is_selected;
    }, ARRAY_FILTER_USE_BOTH);

    return implode(', ', $cards);
  }

  /**
   * Calculate the hash for the request.
   *
   * @var array $var
   *   The data to POST to Quickpay.
   *
   * @return string
   *   The checksum.
   */
  protected function checksumCal($params, $api_key) {
    $flattened_params = $this->flattenParams($params);
    ksort($flattened_params);
    $base = implode(' ', $flattened_params);
    return hash_hmac('sha256', $base, $api_key);
  }

  /**
   * Flatten request parameter array.
   */
  protected function flattenParams($obj, $result = [], $path = []) {
    if (is_array($obj)) {
      foreach ($obj as $k => $v) {
        $result = array_merge($result, $this->flattenParams($v, $result, array_merge($path, [$k])));
      }
    }
    else {
      $result[implode('', array_map(function ($p) {
        return "[{$p}]";
      }, $path))] = $obj;
    }
    return $result;
  }

}
