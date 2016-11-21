<?php

/**
 * @file
 * Contains \Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayException.
 */

namespace Drupal\uc_quickpay\Entity\QuickPayAPI;

/**
 * @class       QuickPay_Exception
 * @extends     Exception
 * @package     QuickPay
 * @category    Class
 * @docs        http://tech.quickpay.net/api/
 */
class QuickPayException extends \Exception {  
  
  /**
   * __Construct function.
   *
   * Redefine the exception so message isn't optional
   *
   * @access public
  */
  public function __construct($message, $code = 0, QuickPayException $previous = null) {
    // Make sure everything is assigned properly
    parent::__construct($message, $code, $previous);
  }
}
