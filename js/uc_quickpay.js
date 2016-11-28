/**
 * @file
 * For create QuickPay token when checkout page appear.
 */

(function ($) {

  'use strict';

  $('input#edit-panes-payment-details-cc-number').removeAttr('name');
  $('input#edit-panes-payment-details-cc-cvv').removeAttr('name');
  $('input#cc-date-year').removeAttr('name');
  var merchantId = drupalSettings.uc_quickpay.merchant_id;
  var agreementId = drupalSettings.uc_quickpay.agreement_id;
  QuickPay.Embedded.Form($('.uc-cart-checkout-form'), {
    merchant_id: merchantId,
    agreement_id: agreementId,
    brandChanged: function (brand) {
      // $('.form-item-panes-payment-details-cc-number').html(brand);
    },
    beforeCreateToken: function (form) {
      $('input.error').removeClass('error');
    },
    failure: function (form, source, message) {
      if (source === 'validation') {
        for (var i = 0; i < message.length; i++) {
          $('input[data-quickpay=' + message[i] + ']').addClass('error');
        }
      }
      else {
        alert(source + ': ' + message);
      }
    },
    success: function (form, token) {
      $('input#edit-panes-payment-details-cc-number').attr('name', 'panes[payment][details][cc_number]');
      $('input#cc-date-year').attr('name', 'panes[payment][details][date_year]');
      $('.uc-cart-checkout-form #edit-continue').attr('name', 'op');
    }
  });
})(jQuery);
