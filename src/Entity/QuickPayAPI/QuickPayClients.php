<?php

namespace Drupal\uc_quickpay\Entity\QuickPayAPI;

/**
 * QuickPay API v10 call client.
 */
class QuickPayClients {

  /**
   * Contains cURL instance.
   *
   * @var ch
   *   public character variable.
   */
  public $ch;

  /**
   * Contains the authentication string.
   *
   * @var authrstring
   *   protected string variable
   */
  protected $authrstring;

  /**
   * Instantiate object.
   */
  public function __construct($authrstring = '') {
    // Check if lib cURL is enabled.
    if (!function_exists('curl_init')) {
      throw new QuickPayException('Lib cURL must be enabled on the server');
    }
    // Set auth string property.
    $this->authrstring = $authrstring;
    // Instantiate cURL object.
    $this->authenticate();
  }

  /**
   * Shutdown function.
   *
   * Closes the current cURL connection.
   */
  public function shutdown() {
    if (!empty($this->ch)) {
      curl_close($this->ch);
    }
  }

  /**
   * Authenticate function.
   *
   * Create a cURL instance with authentication headers.
   */
  protected function authenticate() {
    $this->ch = curl_init();
    $headers = array(
      'Accept-Version: v10',
      'Accept: application/json',
    );
    if (!empty($this->authrstring)) {
      $headers[] = 'Authorization: Basic ' . base64_encode($this->authrstring);
    }
    $options = array(
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
      CURLOPT_HTTPHEADER => $headers,
    );
    curl_setopt_array($this->ch, $options);
  }

}
