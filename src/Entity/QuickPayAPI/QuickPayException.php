<?php

namespace Drupal\uc_quickpay\Entity\QuickPayAPI;

/**
 * QuickPay v10 Exception.
 */
class QuickPayException extends \Exception {

  /**
   * Construct function.
   *
   * @todo Redefine the exception so message isn't optional.
   */
  public function __construct($message, $code = 0, Exception $previous = NULL) {
    // Make sure everything is assigned properly.
    parent::__construct($message, $code, $previous);
    $this->setConfiguration($message);
  }

}
