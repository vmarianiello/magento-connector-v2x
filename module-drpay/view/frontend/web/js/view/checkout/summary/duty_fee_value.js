/**
 *
 * Control to render value in Cart/Checkout for Duty Fee item
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Catalog/js/price-utils',
        'Magento_Customer/js/customer-data',
        'Magento_Ui/js/view/messages',
        'mage/translate'
    ],
    function ($,Component,quote,totals,priceUtils,customerData) {
        "use strict";
        return Component.extend({
            defaults: {
                template: 'Digitalriver_DrPay/checkout/summary/duty_fee_value'
            },
            totals: quote.getTotals(),
            isDisplayedDutyFeeTotal : function () {
                if(document.referrer.includes("checkout") && window.checkoutConfig.drQuote.dr_error_quote.is_dr_quote_error == true) {
                    let msg = $.mage.__('One or more items in your cart cannot be purchased at this time. Please wait a minute and try placing your order again.');
                    customerData.set('messages', {
                        messages: [{
                            text: msg,
                            type: 'error'
                        }]
                    });
                }
                if (!totals.getSegment('dr_ior').value) {
                    return false;
                } else {
                    return true;
                }
            },
            getDutyFeeTotal : function () {
                if (!totals.getSegment('dr_duty_fee').value || totals.getSegment('dr_duty_fee').value === '0.0000') {
                    if(totals.getSegment('dr_ior').value){
                        return this.getFormattedPrice(0);
                    }else {
                        return 0;
                    }
                } else {
                    var price = totals.getSegment('dr_duty_fee').value;
                    return this.getFormattedPrice(price);
                }
            }
        });
    }
);
