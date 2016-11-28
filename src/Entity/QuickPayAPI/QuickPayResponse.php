<?php

namespace Drupal\uc_quickpay\Entity\QuickPayAPI;

/**
 * QuickPay Response class.
 */
class QuickPayResponse {

  /**
   * HTTP status code of request.
   *
   * @var int
   */
  protected $statuscode;

  /**
   * The headers sent during the request.
   *
   * @var string
   */
  protected $sentheaders;

  /**
   * The headers received during the request.
   *
   * @var string
   */
  protected $receivedheaders;

  /**
   * Response body of last request.
   *
   * @var string
   */
  protected $responsedata;

  /**
   * Instantiates a new response object.
   *
   * @var int statuscode, string sentheader, string receivedheader, string responsedata.
   *   The HTTP status code.
   */
  public function __construct($statuscode, $sentheaders, $receivedheaders, $responsedata) {
    $this->statuscode = $statuscode;
    $this->sentheaders = $sentheaders;
    $this->receivedheaders = $receivedheaders;
    $this->responsedata = $responsedata;
  }

  /**
   * Returns the HTTP status code, headers and response body.
   *
   * Usage: list($statuscode, $headers, $response_body) = $response->as_raw().
   *
   * @var boolan $keep_authorization_value Normally the value of the,
   *   Authorization: header is masked. True keeps the sent value.
   *
   * @return array[integer, string[], string]
   *   Return value asRaw.
   */
  public function asRaw($keep_authorization_value = FALSE) {
    // To avoid unintentional logging of credentials the default is to mask the value of the Authorization: header.
    if ($keep_authorization_value) {
      $sentheaders = $this->sentheaders;
    }
    else {
      // Avoid dependency on mbstring.
      $lines = explode("\n", $this->sentheaders);
      foreach ($lines as &$line) {
        if (strpos($line, 'Authorization: ') === 0) {
          $line = 'Authorization: <hidden by default>';
        }
      }
      $sentheaders = implode("\n", $lines);
    }
    return array(
      $this->statuscode,
      array(
        'sent' => $sentheaders,
        'received' => $this->receivedheaders,
      ),
      $this->responsedata,
    );
  }

  /**
   * Returns the response body as an array.
   *
   * @return array
   *   Get response asArray.
   */
  public function asArray() {
    if ($response = json_decode($this->responsedata, TRUE)) {
      return $response;
    }
    return array();
  }

  /**
   * Returns the response body as an array.
   *
   * @return \stdClass
   *   Return response as object form json.
   */
  public function asObject() {
    if ($response = json_decode($this->responsedata)) {
      return $response;
    }
    return new \stdClass();
  }

  /**
   * Returns the http_status code.
   *
   * @return int
   *   HttpStatus code.
   */
  public function httpStatus() {
    return $this->statuscode;
  }

  /**
   * Checks if the http status code indicates a successful or an error response.
   *
   * @return bool
   *   IsSuccess response code.
   */
  public function isSuccess() {
    if ($this->statuscode > 299) {
      return FALSE;
    }
    return TRUE;
  }

}
