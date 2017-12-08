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
   * Returns the set of card types which are used by this payment method.
   *
   * @return array
   *   An array with keys as needed by the chargeCard() method and values
   *   that can be displayed to the customer.
   */
  protected function getEnabledTypes() {
    return [
      'maestro'    => $this->t('Maestro'),
      'visa'       => $this->t('Visa'),
      'mastercard' => $this->t('MasterCard'),
      'amex'       => $this->t('American Express'),
      'dankort'    => $this->t('Dankort'),
      'diners'     => $this->t('Diners'),
      'mobilepay'  => $this->t('MobilePay Online'),
      'sofort'     => $this->t('Sofort'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel($label) {
    $build['label'] = [
      '#prefix' => ' ',
      '#plain_text' => $label,
      '#suffix' => ' ',
    ];
    $cc_types = $this->getEnabledTypes();
    foreach ($cc_types as $type => $description) {
      $build['image'][$type] = [
        '#theme' => 'image',
        '#uri' => drupal_get_path('module', 'uc_quickpay') . '/images/' . $type . '.gif',
        '#alt' => $description,
        '#attributes' => ['class' => ['uc-quickpay-form', 'uc-quickpay-cctype-' . $type]],
      ];
    }
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
      ],
      'language'            => '',
      'autocapture'         => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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
      '#description' => $this->t('This is Merchant Private Key.'),
      '#required' => TRUE,
    ];
    $form['api']['agreement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agreement ID'),
      '#default_value' => $this->configuration['api']['agreement_id'],
      '#description' => $this->t('This is the User Agreement id. The checksum must be signed with the API-key belonging to this Agreement.'),
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
    $elements = [
      'merchant_id',
      'private_key',
      'agreement_id',
      'payment_api_key',
      'pre_order_id',
    ];
    foreach ($elements as $element_name) {
      $raw_key = $form_state->getValue(['settings', 'api', $element_name]);
      $sanitized_key = $this->trimKey($raw_key);
      $form_state->setValue(['settings', $element_name], $sanitized_key);
      if (!$this->validateKey($form_state->getValue(['settings', $element_name]))) {
        $form_state->setError($elements, $this->t('@name does not appear to be a valid QuickPay key', ['@name' => $element_name]));
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
    $this->configuration['language'] = $form_state->getValue('language');
    $this->configuration['autocapture'] = $form_state->getValue('autocapture');
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
    if ($this->configuration['autocapture'] != NULL) {
      $data['autocapture'] = $this->configuration['autocapture'] ? 1 : 0;
    }
    $data['variables[uc_order_id]'] = $order->id();
    $data['customer_email'] = $order->getEmail();
    $data['invoice_address[name]'] = $bill_address->first_name . " " . $bill_address->last_name;
    $data['invoice_address[att]'] = $bill_address->street1;
    $data['invoice_address[street]'] = $bill_address->street2;
    $data['invoice_address[zip_code]'] = $bill_address->postal_code;
    $data['invoice_address[city]'] = $bill_address->city;
    $data['invoice_address[region]'] = $bill_address->zone;
    $data['invoice_address[country_code]'] = $country;
    $data['invoice_address[email]'] = $order->getEmail();
    // Static variable for loop.
    $i = 0;
    foreach ($order->products as $item) {
      $data['basket[' . $i . '][qty]'] = $item->qty->value;
      $data['basket[' . $i . '][item_no]'] = $item->model->value;
      $data['basket[' . $i . '][item_name]'] = $item->title->value;
      $data['basket[' . $i . '][item_price]'] = uc_currency_format($item->price->value, FALSE, FALSE, '.');
      $data['basket[' . $i . '][vat_rate]'] = 0.25;
      $i++;
    }
    // Checksum.
    $data['checksum'] = $this->checksumCal($data, $this->configuration['api']['payment_api_key']);
    // Add hidden field with new form.
    foreach ($data as $name => $value) {
      if (!empty($value)) {
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
    $base = implode(" ", $flattened_params);
    return hash_hmac("sha256", $base, $api_key);
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
      $result[implode("", array_map(function ($p) {
        return "[{$p}]";
      }, $path))] = $obj;
    }
    return $result;
  }

}
