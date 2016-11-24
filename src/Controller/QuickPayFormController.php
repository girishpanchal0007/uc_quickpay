<?php

namespace Drupal\uc_quickpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod\QuickPayPaymentForm;

/**
 * Returns response for QuickPay Form Payment Method.
 */
class QuickPayFormController extends ControllerBase {

  /**
   * Handles a complete QuickPay Payments request.
  */
  public function quickPayFormComplete(OrderInterface $uc_order) {
    // Checking current session current order.
    $session = \Drupal::service('session');
    if (!$session->has('cart_order') || intval($session->get('cart_order')) != $uc_order->id()) {
      drupal_set_message($this->t('Thank you for your order! QuickPay will notify you once your payment has been processed.'));
      return $this->redirect('uc_cart.cart');
    }
    // Checking is that payment method is QuickPay Form.
    $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($uc_order);
    if (!$method instanceof QuickPayPaymentForm) {
      return $this->redirect('uc_cart.cart');
    }
    // This lets us know it's a legitimate access of the complete page.
    $session = \Drupal::service('session');
    $message = $this->t('QuickPay Form payment was successfully of : @amount @currency.', ['@amount' => uc_currency_format($uc_order->getTotal(), FALSE, FALSE, FALSE), '@currency' => $uc_order->getCurrency()]);
    // Comment order.
    uc_order_comment_save($uc_order->id(), $uc_order->getOwnerId(), $message, 'admin');
    $session->set('uc_checkout_complete_' . $uc_order->id(), TRUE);
    return $this->redirect('uc_cart.checkout_complete');
  }

  /**
   * Handles a cancel QuickPay Payments request.
  */
  public function quickPayFormCancel(OrderInterface $uc_order) {
    // Checking is that payment method is QuickPay Form.
    $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($uc_order);
    if (!$method instanceof QuickPayPaymentForm) {
      return $this->redirect('uc_cart.cart');
    }
    // Checking current session current order.
    $session = \Drupal::service('session');
    $message = $this->t('Quick Pay Form payment was cancelled occurred some unnecessary action: @amount @currency.', ['@amount' => uc_currency_format($uc_order->getTotal(), FALSE, FALSE, FALSE), '@currency' => $uc_order->getCurrency()]);
    // Comment order.
    uc_order_comment_save($uc_order->id(), $uc_order->getOwnerId(), $message, 'admin');
    // Remove session for the current order.
    $session->remove('cart_order');
    drupal_set_message($this->t('An error has occurred in your QuickPay payment. Please review your cart and try again.'));
    return $this->redirect('uc_cart.cart');
  }
  
}
