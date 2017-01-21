<?php

namespace Drupal\uc_quickpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_payment\Plugin\PaymentMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(PaymentMethodManager $payment_method_manager, SessionInterface $session, LoggerInterface $logger) {
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
   * Handle Callback from QUickPay payment gateway.
   */
  public function quickPayCallback() {
    // Get order id from cart order.
    $order_id = $this->session->get('cart_order');
    // Load order using order id.
    $order = Order::load($order_id);
    // Get private key configuration.
    $plugin = $this->paymentMethodManager->createFromOrder($order);
    $adminconfiguration = $plugin->getConfiguration();
    // Get request body.
    $request_body = file_get_contents("php://input");
    // Checking checksum.
    $checksum = $this->callbackChecksum($request_body, $adminconfiguration['api']['private_key']);
    if ($checksum == $_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"]) {
      // Store callback data.
      $data = json_decode($request_body, TRUE);
      if (!isset($data['id'])) {
        $this->log->error('QuickPay callback payment_id is not found.');
        return;
      }
      // Get string length.
      $order_length = strlen((string) $order->id());
      $orderID = substr($data['order_id'], -$order_length);
      if ($orderID != $order->id()) {
        $this->log->error('QuickPay callback order_id is not matched.');
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
          ->fields(array(
            'order_id' => $orderID,
            'payment_id' => $payment_id,
            'merchant_id' => $merchant_id,
            'payment_type' => $payment_type,
            'payment_brand' => $payment_brand,
            'payment_amount' => $payment_amount,
            'payment_status' => $payment_status,
            'customer_email' => $payment_email,
            'created_at' => REQUEST_TIME,
          ))
          ->execute();
      }
      else {
        uc_order_comment_save($order->id(), 0, $this->t("QuickPay payment is not approved by QuickPay. You need to contact with site admin"), 'admin');
      }
    }
    else {
      uc_order_comment_save($order->id(), 0, $this->t('QuickPay payment is not match with callback response. You need to contact with site admin.',
        [
          '@amount' => uc_currency_format($payment_amount, FALSE),
          '@currency' => $data['currency'],
        ]
      ));
    }
  }

  /**
   * Create checksum to compare with response checksum.
   */
  protected function callbackChecksum($base, $private_key) {
    return hash_hmac("sha256", $base, $private_key);
  }

}
