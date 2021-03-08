/**
 *
 * Control to render label in Cart/Checkout for Duty Fee item
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
define(
    [
        'Digitalriver_DrPay/js/view/checkout/summary/duty_fee_value',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals'
    ],
    function (Component, quote, totals, dutyFreeValue) {
        'use strict';
        return Component.extend({
            /**
             * @override
             */
            totals: quote.getTotals(),isDisplayedDutyFeeTotal: function () {
                let urlDrQuoteError = window.BASE_URL+'/drpay/quoteerror/index';
                jQuery.ajax({
                    url: urlDrQuoteError,
                    type: 'GET',
                    complete: function(response){
                        let drQuoteError = response.responseJSON;
                        if(drQuoteError[0].dr_quote_error){
                            jQuery.mage.redirect('cart');
                        }
                    },
                    error: function (xhr, status, errorThrown) {
                        window.location.reload();
                    }
                });
                if (!totals.getSegment('dr_ior').value) {
                    return false;
                } else {
                    return true;
                }
            }
        });
    }
);
