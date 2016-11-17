jQuery(document).ready(function($) {
    jQuery('input#edit-panes-payment-details-cc-number').removeAttr('name');
    jQuery('input#edit-panes-payment-details-cc-cvv').removeAttr('name');
    jQuery('input#cc-date-year').removeAttr('name');
});

jQuery(document).ready(function($) {
    var merchantId = drupalSettings.uc_quickpay.merchant_id;
    var agreementId = drupalSettings.uc_quickpay.agreement_id;
    QuickPay.Embedded.Form(jQuery('.uc-cart-checkout-form'), {   
        merchant_id: merchantId,
        agreement_id: agreementId,
        brandChanged: function(brand) {
            //jQuery('.form-item-panes-payment-details-cc-number').html(brand);
        },
        beforeCreateToken: function(form) {
        	jQuery('input.error').removeClass('error');
            jQuery.each(jQuery("input[type='hidden']"), function(index, val) {
                if(jQuery(this).data('quickpay') == "expiration" ){
                    jQuery(this).removeClass('error');
                    jQuery(this).addClass('valid');
                }
            });
        },
        failure: function(form, source, message) {
        	if (source === 'validation') {
          	    for (var i = 0; i < message.length; i++) {
            	   jQuery('input[data-quickpay=' + message[i] + ']').addClass('error');
          	    }
        	} else {
          	    alert(source + ': ' + message);
        	}        	
        },
        success: function(form, token){
            jQuery('input#edit-panes-payment-details-cc-number').attr('name', 'panes[payment][details][cc_number]');
            jQuery('input#cc-date-year').attr('name', 'panes[payment][details][date_year]');
            jQuery('.uc-cart-checkout-form #edit-continue').attr('name', 'op');
        }

    });
});
