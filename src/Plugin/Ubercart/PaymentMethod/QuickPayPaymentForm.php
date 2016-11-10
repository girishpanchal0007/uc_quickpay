<?php 
namespace Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\uc_payment\PaymentMethodPluginBase;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_quickpay\Entity\QuickPay;
use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayException;

/**
 * QuickPay Ubercart gateway payment method.
 *
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
		$build['label'] = array(
		  '#prefix' => ' ',
		  '#plain_text' => $label,
		);		
		$cc_types = $this->getEnabledTypes();
		foreach ($cc_types as $type => $description) {
		  $build['image'][$type] = array(
		    '#theme' => 'image',
		    '#uri' => drupal_get_path('module', 'uc_quickpay') . '/images/' . $type . '.gif',
		    '#alt' => $description,
		    '#attributes' => array('class' => array('uc-quickpay-cctype', 'uc-quickpay-cctype-' . $type)),
		  );
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
                'user_api_key'    => '',
                'agreement_id'    => '',
                'payment_api_key' => '',
            ],
            'language'            => '',
            'autocapture'         => '',
            'callbacks' => [
                'continue_url'    => '',
                'cancel_url'      => '',
            ]
    	];
  	}
  	/**
   	* {@inheritdoc}
   	*/
  	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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

        $form['language'] = array(
            '#type' => 'select',
            '#options' => array(
                    'en' => 'English',
                    'da' => 'Danish',
                    'de' => 'German',
                    'fr' => 'French',
                    'it' => 'Italian',
                    'no' => 'Norwegian',
                    'nl' => 'Dutch',
                    'pl' => 'Polish',
                    'se' => 'Swedish',
                ),
            '#title' => $this->t('Payment Language'),
            '#default_value' => $this->configuration['language'],
            '#description' => $this->t('Set the language of the user interface. Defaults to English..'),
        );

        $form['autocapture'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Autocapture'),
            '#default_value' => $this->configuration['autocapture'],
            '#description' => $this->t('If set to 1, the payment will be captured automatically.'),
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
            '#description' => $this->t('The customer will be redirected to this URL upon a succesful payment. No data will be send to this URL..'),
        );

        $form['callbacks']['cancel_url'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Cancel URL'),
            '#default_value' => $this->configuration['callbacks']['cancel_url'],
            '#description' => $this->t('The customer will be redirected to this URL if the customer cancels the payment. No data will be send to this URL..'),    
        );
		return $form;
  	}

  	public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
        $elements = ['merchant_id', 'user_api_key', 'agreement_id', 'payment_api_key'];
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
        foreach (['merchant_id', 'user_api_key', 'agreement_id', 'payment_api_key'] as $item) {
            $this->configuration['api'][$item] = $form_state->getValue(['settings', 'api', $item]);
        }
        $this->configuration['language'] = $form_state->getValue('language');
        $this->configuration['autocapture'] = $form_state->getValue('autocapture');
        $this->configuration['callbacks']['continue_url'] = $form_state->getValue(['settings', 'callbacks', 'continue_url']);
        $this->configuration['callbacks']['cancel_url'] = $form_state->getValue(['settings', 'callbacks', 'cancel_url']);

        return parent::submitConfigurationForm($form, $form_state);
    }

    /**
    * {@inheritdoc}
    */
    public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
        global $base_url;

        // Get billing address object.
        $bill_address = $order->getAddress('billing');
        $country = $bill_address->country;
        // Formate current with multiply 100.
        $amount_currency = uc_currency_format($order->getTotal(), FALSE, FALSE, FALSE);

        $data = array();

        // required parameter
        $data['version'] = QUICKPAY_VERSION;
        $data['merchant_id'] = $this->configuration['api']['merchant_id'];
        $data['agreement_id'] = $this->configuration['api']['agreement_id'];
        $data['order_id'] = $this->configuration['api']['pre_order_id'] . $order->id();
        $data['amount'] = $amount_currency;
        $data['currency'] = $order->getCurrency();

        $data['continueurl'] = Url::fromRoute('uc_quickpay.qpf_complete', ['uc_order' => $order->id()], ['absolute' => TRUE])->toString();
        $data['cancelurl'] =  Url::fromRoute('uc_cart.checkout_review', [], ['absolute' => TRUE])->toString();
       	//$data['callbackurl'] = Url::fromRoute('uc_quickpay.callback', ['uc_order' => $order->id()], ['absolute' => TRUE])->toString();

        $data['language'] = $this->configuration['language'];
        if($this->configuration['autocapture'] != NULL){
            $data['autocapture'] = $this->configuration['autocapture'] ? '1' : '0';
        }
        $data['customer_email'] = $order->getEmail();
        

        $data['invoice_address[name]'] = $bill_address->first_name . " " . $bill_address->last_name;
        $data['invoice_address[att]'] = $bill_address->street1;
        $data['invoice_address[street]'] = $bill_address->street2;
        $data['invoice_address[zip_code]'] = $bill_address->postal_code;
        $data['invoice_address[city]'] = $bill_address->city;
        $data['invoice_address[region]'] = $bill_address->zone;
        $data['invoice_address[email]'] = $order->getEmail();
        // $codes = json_decode(file_get_contents('http://country.io/iso3.json'), true);
        // $iso3_to_iso2 = array();
        // foreach($codes as $iso2 => $iso3) {
        //     if(substr($iso3, 0, 2) == $country){
        //         $iso3_to_iso2 = $iso3;
        //     }
        // }
        //$data['invoice_address[country_code]'] = $iso3_to_iso2;
        

        $data['checksum'] = $this->sign($data, $this->configuration['api']['payment_api_key']);;            
        
        // Add hidden field with new form
        foreach ($data as $name => $value) {
            if (!empty($value)) {
                $form[$name] = array('#type' => 'hidden', '#value' => $value);
            }
        }

        $form['#action'] = 'https://payment.quickpay.net';
       	$form['actions'] = array('#type' => 'actions');

        $form['actions']['submit'] = array(
          	'#type' => 'submit',
          	'#value' => $this->t('QuickPay Payment'),
          	'#id' => 'quickpay-submit',
        );

        return $form;
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
      * @param array $params
      *   The data to POST to Quickpay.
      *
      * @return string
      *   The checksum.
      *
      * @see http://tech.quickpay.net/payments/hosted/#checksum
    */
    protected function sign($params, $api_key) {
	    $flattened_params = $this->flatten_params($params);
	    ksort($flattened_params);
	    $base = implode(" ", $flattened_params);

	    return hash_hmac("sha256", $base, $api_key);
	}

    /**
      * Flatten request parameter array.
    */
    protected function flatten_params($obj, $result = array(), $path = array()) {
	    if (is_array($obj)) {
	        foreach ($obj as $k => $v) {
	            $result = array_merge($result, $this->flatten_params($v, $result, array_merge($path, array($k))));
	        }
	    } else {
	        $result[implode("", array_map(function($p) { return "[{$p}]"; }, $path))] = $obj;
	    }

	    return $result;
	}

}