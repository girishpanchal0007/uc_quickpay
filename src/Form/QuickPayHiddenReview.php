<?php

namespace Drupal\uc_quickpay\Form;

use Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod;
use Drupal\uc_quickpay\Plugin\Ubercart\PaymentMethod\QuickPayGateway;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;

/**
 * Returns the form for the custom Review Payment screen for Express Checkout.
 */
class QuickPayHiddenReview extends FormBase {

    /**
      * The order that is being reviewed.
      *
      * @var \Drupal\uc_order\OrderInterface
    */
    protected $order;

    /**
      * {@inheritdoc}
    */
    public function getFormId() {
        return 'uc_quickpay_review_form';
    }

    /**
      * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
        $this->order = $order;
        
        return $form;
    }

    /**
       * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        \Drupal::service('plugin.manager.uc_payment.method')
            ->createFromOrder($this->order)
            ->submitExpressReviewForm($form, $form_state, $this->order);

        \Drupal::service('session')->set('uc_checkout_review_' . $this->order->id(), TRUE);
        $form_state->setRedirect('uc_cart.checkout_review');
    } 

}
