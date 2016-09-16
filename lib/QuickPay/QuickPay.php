<?php
namespace Drupal\uc_quickpay\lib\QuickPay;

use Drupal\uc_quickpay\lib\QuickPay\API\Client;
use Drupal\uc_quickpay\lib\QuickPay\API\Request;

class QuickPay
{
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
        $client = new Client($auth_string);
        $this->request = new Request($client);
    }
}
