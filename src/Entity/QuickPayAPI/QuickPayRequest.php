<?php

namespace Drupal\uc_quickpay\Entity\QuickPayAPI;

use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayResponse;
use Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayConstants;

/**
 * Provides the Mollie profile add/edit form.
 */
class QuickPayRequest {

  /**
   * Contains QuickPay_Client instance.
   *
   * @var \Drupal\uc_quickpay\Entity\QuickPayAPI\QuickPayRequest
   */
  protected $client;

  /**
   * Construct function.
   *
   * Instantiates the object.
   */
  public function __construct($client) {
    $this->client = $client;
  }

  /**
   * GET function.
   *
   * Performs an API GET request.
   *
   * @var $path $query
   *   Resonse $path for string and $query for array.
   *
   * @return Response
   *   Get request when function is calling.
   */
  public function get($path, $query = array()) {
    // Add query parameters to $path?.
    if (!empty($query)) {
      $strpos = strpos($path, '?');
      if ($strpos === FALSE) {
        $path .= '?' . http_build_query($query, '', '&');
      }
      else {
        $path .= ini_get('arg_separator.output') . http_build_query($query, '', '&');
      }
    }
    // Set the request params.
    $this->setUrl($path);
    // Start the request and return the response.
    return $this->execute('GET');
  }

  /**
   * POST function.
   *
   * Performs an API POST request.
   *
   * @return Response
   *   Post request when function is calling.
   */
  public function post($path, $form = array()) {
    // Set the request params.
    $this->setUrl($path);
    // Start the request and return the response.
    return $this->execute('POST', $form);
  }

  /**
   * PUT function.
   *
   * Performs an API PUT request.
   *
   * @return Response
   *   Put request when function is calling.
   */
  public function put($path, $form = array()) {
    // Set the request params.
    $this->setUrl($path);
    // Start the request and return the response.
    return $this->execute('PUT', $form);
  }

  /**
   * PATCH function.
   *
   * Performs an API PATCH request.
   *
   * @return Response
   *   Patch request when function is calling.
   */
  public function patch($path, $form = array()) {
    // Set the request params.
    $this->setUrl($path);
    // Start the request and return the response.
    return $this->execute('PATCH', $form);
  }

  /**
   * DELETE function.
   *
   * Performs an API DELETE request.
   *
   * @return Response
   *   Delete request when function is calling.
   */
  public function delete($path, $form = array()) {
    // Set the request params.
    $this->setUrl($path);
    // Start the request and return the response.
    return $this->execute('DELETE', $form);
  }

  /**
   * SetUrl function.
   *
   * Takes an API request string and appends it to the API url.
   */
  protected function setUrl($params) {
    curl_setopt($this->client->curl, CURLOPT_URL, QuickPayConstants::API_URL . trim($params, '/'));
  }

  /**
   * EXECUTE function.
   *
   * Performs the prepared API request.
   *
   * @var $request_type
   *   String for request and array for the form.
   *
   * @return Response
   *   Execute request when function is calling.
   */
  protected function execute($request_type, $form = array()) {
    // Set the HTTP request type.
    curl_setopt($this->client->curl, CURLOPT_CUSTOMREQUEST, $request_type);
    // Additional data is delivered,we will send it along with the API request.
    if (is_array($form) && !empty($form)) {
      curl_setopt($this->client->curl, CURLOPT_POSTFIELDS, http_build_query($form, '', '&'));
    }
    // Store received headers in temporary memory file, remember sent headers.
    $fh_header = fopen('php://temp', 'w+');
    curl_setopt($this->client->curl, CURLOPT_WRITEHEADER, $fh_header);
    curl_setopt($this->client->curl, CURLINFO_HEADER_OUT, TRUE);
    // Execute the request.
    $response_data = curl_exec($this->client->curl);
    if (curl_errno($this->client->curl) !== 0) {
      // An error occurred.
      fclose($fh_header);
      throw new QuickPayException(curl_error($this->client->curl), curl_errno($this->client->curl));
    }
    // Grab the headers.
    $sent_headers = curl_getinfo($this->client->curl, CURLINFO_HEADER_OUT);
    rewind($fh_header);
    $received_headers = stream_get_contents($fh_header);
    fclose($fh_header);
    // Retrieve the HTTP response code.
    $response_code = (int) curl_getinfo($this->client->curl, CURLINFO_HTTP_CODE);
    // Return the response object.
    return new QuickPayResponse($response_code, $sent_headers, $received_headers, $response_data);
  }

}
