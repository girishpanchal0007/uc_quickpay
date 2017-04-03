<?php

namespace Drupal\uc_quickpay\Entity\QuickPayAPI;

/**
 * QuickPay API v10 call client.
 */
class QuickPayClients {

  /**
   * Contains cURL instance.
   *
   * @var curl
   *   public character variable.
   */
  public $curl;

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
    // Set auth string property.
    $this->authrstring = $authrstring;
    // Instantiate cURL object.
    $this->authenticate();
  }

  /**
   * Shutdown function.
   *
   * @todo Closes the current cURL connection.
   */
  public function shutdown() {
    if (!empty($this->curl)) {
      curl_close($this->curl);
    }
  }

  /**
   * Authenticate function.
   *
   * @todo Create a cURL instance with authentication headers.
   */
  protected function authenticate() {
    $this->curl = curl_init();
    $headers = [
      'Accept-Version: v10',
      'Accept: application/json',
    ];
    if (!empty($this->authrstring)) {
      $headers[] = 'Authorization: Basic ' . base64_encode($this->authrstring);
    }
    $options = [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
      CURLOPT_HTTPHEADER => $headers,
    ];
    curl_setopt_array($this->curl, $options);
  }

}
