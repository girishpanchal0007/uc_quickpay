<?php
/**
 * @file
 * Definition of Drupal\uc_quickpay\Entity\QuickPay.
 */

namespace Drupal\uc_quickpay\Entity;

use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayClients;
use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayRequest;

class QuickPay {

  	/**
     * Contains the QuickPay_Request object
     *
     * @access public
    **/
    public $request;

    /**
    * __construct function.
    *
    * Instantiates the main class.
    * Creates a client which is passed to the request construct.
    *
    * @auth_string string Authentication string for QuickPay
    *
    * @access public
    */
    public function __construct($auth_string = '')
    {
        $client = new QuickPayClients($auth_string);
        $this->request = new QuickPayRequest($client);
    }

}
