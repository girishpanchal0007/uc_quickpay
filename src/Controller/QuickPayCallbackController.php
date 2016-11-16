<?php
namespace Drupal\uc_quickpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\OrderInterface;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Returns responses for QuickPay Form Payment Method.
class QuickPayCallbackController extends ControllerBase {

	// public function quickPayCallback() {
    /**
    * Processes the IPN HTTP request.
    *
    * @param \Symfony\Component\HttpFoundation\Request $request
    *   The request of the page.
    *
    * @return \Symfony\Component\HttpFoundation\Response
    *   An empty Response with HTTP status code 200.
    */
    public function quickPayCallback(Request $request) {
        $this->processCallback($request->request->all());
        return new Response();
    }

    protected function processCallback($callback) {
        mail('girish@krishaweb.com', 'Ubercart', $callback);        
        if(!empty($callback)){
            $tree =  (string) $callback;
            mail('girish@krishaweb.com', 'Ubercart', $tree);
        }
    }

    //$responses = new Response();
    // $request_body = file_get_contents("php://input");
    // $checksum  = $this->sign($request_body, "70dcb1f48c446d000bb1f4c99ffc1b4bc5fce1d0be1588a04d09b5df91a0a1e6");
    // if ($checksum == $_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"]) {
    //     mail('girish@krishaweb.com', 'Ubercart', 'success');
    //     $response = \Drupal::httpClient()->request->get('/payments');
    //     $response1  = (string)$response;
    //     // Logs a notice
    //     \Drupal::logger('uc_quickpay')->notice($response1);
    //     mail('girish@krishaweb.com', 'Ubercart', $response1);
    //     // Logs an error
    //     \Drupal::logger('uc_quickpay')->error($response1);
    // } else {
    //   // Request is NOT authenticated
    // }
	// mail('girish@krishaweb.com', 'Ubercart', $responses);

    // private function sign($base, $private_key) {
    //     return hash_hmac("sha256", $base, $private_key);
    // }
}