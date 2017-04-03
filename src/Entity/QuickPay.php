<?php

namespace Drupal\uc_quickpay\Entity;

use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayClients;
use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayRequest;

/**
 * QuickPay v10 library.
 */
class QuickPay {

  /**
   * Contains the QuickPay_Request object.
   *
   * @var request
   * QuickPay request variable.
   */
  public $request;

  /**
   * Construct function.
   *
   * Instantiates the main class.
   *
   * @todo Creates a client which is passed to the request construct.
   */
  public function __construct($authrstring = '') {
    $client = new QuickPayClients($authrstring);
    $this->request = new QuickPayRequest($client);
  }

}
