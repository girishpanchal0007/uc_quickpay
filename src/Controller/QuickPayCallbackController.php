<?php

namespace Drupal\uc_quickpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\Plugin\PaymentMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Returns response for QuickPay Form Payment Method.
 */
class QuickPayCallbackController extends ControllerBase {

  /**
   * The payment method manager.
   *
   * @var \Drupal\uc_payment\Plugin\PaymentMethodManager
   */
  protected $paymentMethodManager;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * The error and warnings logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  /**
   * Constructs a QuickPayFormController.
   *
   * @param \Drupal\uc_payment\Plugin\PaymentMethodManager $payment_method_manager
   *   The payment method.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param Drupal\Core\Logger\LoggerChannelFactory $logger
   *   The logger.
   */
  public function __construct(PaymentMethodManager $payment_method_manager, SessionInterface $session, LoggerChannelFactory $logger) {
    $this->paymentMethodManager = $payment_method_manager;
    $this->session = $session;
    $this->log = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.uc_payment.method'),
      $container->get('session'),
      $container->get('logger.factory')
    );
  }

  /**
   * Quickpay callback request.
   *
   * @todo Handle Callback from QUickPay payment gateway.
   */
  public function quickPayCallback(OrderInterface $uc_order) {
    // Get private key configuration.
    $plugin = $this->paymentMethodManager->createFromOrder($uc_order);
    $adminconfiguration = $plugin->getConfiguration();
    // Get request body.
    $request_body = file_get_contents("php://input");
    // Checking checksum.
    $checksum = $this->callbackChecksum($request_body, $adminconfiguration['api']['private_key']);
    if (isset($_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"])) {
      if ($checksum == $_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"]) {
        // Store callback data.
        $data = json_decode($request_body, TRUE);
        if (!isset($data['id'])) {
          $this->log->error('QuickPay callback response doesn&apos;t have payment id.');
          return;
        }
        // Get string length.
        $order_length = strlen((string) $uc_order->id());
        $orderID = substr($data['order_id'], -$order_length);
        if ($orderID != $uc_order->id()) {
          $this->log->error('QuickPay callback response order id is not matched with current order id.');
          return;
        }
        if ($data['operations'][0]['aq_status_msg'] == "Approved") {
          $payment_id = $data['id'];
          $merchant_id = $data['merchant_id'];
          $payment_type = $data['metadata']['type'];
          $payment_brand = $data['metadata']['brand'];
          $payment_amount = $data['operations'][0]['amount'];
          $payment_status = $data['operations'][0]['aq_status_msg'];
          $payment_email = $data['invoice_address']['email'];
          // Callback response enter to the database.
          db_insert('uc_payment_quickpay_callback')
            ->fields([
              'order_id' => $orderID,
              'payment_id' => $payment_id,
              'merchant_id' => $merchant_id,
              'payment_type' => $payment_type,
              'payment_brand' => $payment_brand,
              'payment_amount' => $payment_amount,
              'payment_status' => $payment_status,
              'customer_email' => $payment_email,
              'created_at' => REQUEST_TIME,
            ])
            ->execute();
        }
        else {
          uc_order_comment_save($uc_order->id(), 0, $this->t("The Quickpay response is not matched with the sent data. You need to contact the site administrator."), 'admin');
        }
      }
    }
    else {
      uc_order_comment_save($uc_order->id(), 0, $this->t('QuickPay server is not responded. You need to contact with site administrator.'));
      return FALSE;
    }
  }

  /**
   * Checksum function.
   *
   * @todo Create checksum to compare with response checksum.
   */
  protected function callbackChecksum($base, $private_key) {
    return hash_hmac("sha256", $base, $private_key);
  }

}
