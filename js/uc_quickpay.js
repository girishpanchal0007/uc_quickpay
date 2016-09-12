jQuery(document).ready(function($) {
    jQuery('#edit-panes-payment-details-cc-number').attr('data-quickpay', 'cardnumber');
    jQuery('#edit-panes-payment-details-cc-exp-month').attr('data-quickpay', 'expiration');
    jQuery('#edit-panes-payment-details-cc-exp-year').attr('data-quickpay', 'expiration');
    jQuery('#edit-panes-payment-details-cc-cvv').attr('data-quickpay', 'cvd');
});

(function ($) {
    var MerchantID = drupalSettings.uc_quickpay.merchant_id;
    var AgreementID = drupalSettings.uc_quickpay.agreement_id;
    QuickPay.Embedded.Form(jQuery('#uc-cart-checkout-form'), {      
        merchant_id: MerchantID,
        agreement_id: AgreementID,
        brandChanged: function(brand) {
            jQuery('.card-brand').html(brand);
        },
        beforeCreateToken: function(form) {
        	jQuery('input.error').removeClass('error');
        	jQuery('#uc-cart-checkout-form #edit-continue').html('Please wait...');
        },
        failure: function(form, source, message) {
        	if (source === 'validation') {
          	    for (var i = 0; i < message.length; i++) {
            	   jQuery('input[data-quickpay=' + message[i] + ']').addClass('error');
          	    }
        	} else {
          	    alert(source + ': ' + message);
        	}
        	jQuery('#uc-cart-checkout-form #edit-continue').html('Pay');
        }
    });
}(jQuery));