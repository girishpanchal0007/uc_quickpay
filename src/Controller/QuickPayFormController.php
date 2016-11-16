<?php
namespace Drupal\uc_quickpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod\QuickPayPaymentForm;

//Returns responses for QuickPay Form Payment Method.
class QuickPayFormController extends ControllerBase {
    /**
       * Handles a complete QuickPay Payments request.
       *
       * @return \Symfony\Component\HttpFoundation\RedirectResponse
       *   A redirect to the cart or checkout complete page.
    */
    public function quickPayFormComplete(OrderInterface $uc_order) {
        // check current session for order
        $session = \Drupal::service('session');
        if (!$session->has('cart_order') || intval($session->get('cart_order')) != $uc_order->id()) {
            drupal_set_message($this->t('Thank you for your order! QuickPay will notify us once your payment has been processed.'));
            return $this->redirect('uc_cart.cart');
        }

        // checking is that payment method is QuickPay Form.
        $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($uc_order);
        if (!$method instanceof QuickPayPaymentForm) {
            return $this->redirect('uc_cart.cart');
        }

        // This lets us know it's a legitimate access of the complete page.
        $session = \Drupal::service('session');
        $session->set('uc_checkout_complete_' . $uc_order->id(), TRUE);

        return $this->redirect('uc_cart.checkout_complete');
    }

    public function quickPayFormCancel(OrderInterface $uc_order) {
        // checking is that payment method is QuickPay Form.
        $method = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($uc_order);
        if (!$method instanceof QuickPayPaymentForm) {
            return $this->redirect('uc_cart.cart');
        }

        $session = \Drupal::service('session');

        $message = $this->t('Quick Pay form payment was cancelled occurred some unnecessary action: @OrderID, OrderId', ['@OrderID' => $uc_order->id()]);
        uc_order_comment_save($uc_order->id(), $uc_order->getOwnerId(), $message, 'admin');  

        $session->remove('cart_order');
        drupal_set_message($this->t('An error has occurred in your QuickPay payment. Please review your cart and try again.'));
        return $this->redirect('uc_cart.cart');
        
    }
}