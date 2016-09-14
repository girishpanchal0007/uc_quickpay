jQuery(document).ready(function($) {
    jQuery('input#edit-panes-payment-details-cc-number').removeAttr('name');
    // jQuery('select#edit-panes-payment-details-cc-exp-month').removeAttr('name');
    // jQuery('select#edit-panes-payment-details-cc-exp-year').removeAttr('name');
    jQuery('input#cc-date-year').removeAttr('name');
    jQuery('input#edit-panes-payment-details-cc-cvv').removeAttr('name');
    // jQuery('#edit-continue').click(function(event) {
    //     /* Act on the event */
    //     var ExMonth = jQuery('#edit-panes-payment-details-cc-exp-month').val();
    //     var ExYear = jQuery('#edit-panes-payment-details-cc-exp-year').val().slice("-2");
    //     if(ExMonth < 10){
    //         jQuery('#cc-date-year').val('0' + ExMonth + '/' + ExYear);
    //     }
    //     else{
    //         jQuery('#cc-date-year').val(ExMonth + '/' + ExYear);  
    //     }
    // });
});

jQuery(document).ready(function($) {
    QuickPay.Embedded.Form(jQuery('.uc-cart-checkout-form'), {   
        merchant_id: 21882,
        agreement_id: 79491,
        brandChanged: function(brand) {
            //jQuery('.form-item-panes-payment-details-cc-number').html(brand);
        },
        beforeCreateToken: function(form) {
        	jQuery('input.error').removeClass('error');
        	jQuery('.uc-cart-checkout-form #edit-continue').html('Please wait...');
        },
        failure: function(form, source, message) {
        	if (source === 'validation') {
          	    for (var i = 0; i < message.length; i++) {
            	   jQuery('input[data-quickpay=' + message[i] + ']').addClass('error');
          	    }
        	} else {
          	    alert(source + ': ' + message);
        	}
        	jQuery('.uc-cart-checkout-form #edit-continue').html('Pay');
        }
    });
});
