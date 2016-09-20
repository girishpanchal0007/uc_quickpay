jQuery(document).ready(function($) {
    jQuery('input#edit-panes-payment-details-cc-number').removeAttr('name');
    // jQuery('select#edit-panes-payment-details-cc-exp-month').removeAttr('name');
    // jQuery('select#edit-panes-payment-details-cc-exp-year').removeAttr('name');
    jQuery('input#edit-panes-payment-details-cc-cvv').removeAttr('name');
    jQuery('input#cc-date-year').removeAttr('name');
    //jQuery("input[name='panes[payment][details][quickpay_submit]").attr('name','op')
    //jQuery('.uc-cart-checkout-form #edit-cancel').attr('disabled', true);
    // jQuery('#edit-panes-payment-details-cc-exp-year').change(function(event) {
    //     /* Act on the event */
    //     var ExMonth = jQuery('#edit-panes-payment-details-cc-exp-month').val();
    //     var ExYear = jQuery('#edit-panes-payment-details-cc-exp-year').val().slice("-2");
    //     if(ExMonth < 10){
    //         jQuery("input[type='hidden']").val('0' + ExMonth + ' / ' + ExYear);
    //     }
    //     else{
    //         jQuery("input[type='hidden']").val(ExMonth + ' / ' + ExYear);  
    //     }
    // });
    // jQuery('#edit-panes-payment-details-cc-exp-year').change(function(event) {
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
        	//jQuery('.uc-cart-checkout-form #edit-continue').html('Please wait...');
        },
        failure: function(form, source, message) {
        	if (source === 'validation') {
          	    for (var i = 0; i < message.length; i++) {
            	   jQuery('input[data-quickpay=' + message[i] + ']').addClass('error');
          	    }
        	} else {
          	    alert(source + ': ' + message);
        	}
        	//jQuery('.uc-cart-checkout-form #edit-continue').html('Pay');
        },
        success: function(form, token){
            jQuery('input#edit-panes-payment-details-cc-number').attr('name', 'panes[payment][details][cc_number]');
            jQuery('.uc-cart-checkout-form #edit-continue').attr('name', 'op');
        }

    });
});
