/**
 *
 * Control to render value in Cart/Checkout for IOR Tax item
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
        'Magento_Catalog/js/price-utils'
    ],
    function ($,Component,quote,totals,priceUtils) {
        "use strict";
        return Component.extend({
            defaults: {
                template: 'Digitalriver_DrPay/checkout/summary/ior_tax_value'
            },
            totals: quote.getTotals(),
            isDisplayedIORTaxTotal : function () {
                if (!totals.getSegment('dr_ior').value) {
                    return false;
                } else {
                    return true;
                }
            },
            getIORTaxTotal : function () {
                if (!totals.getSegment('dr_ior_tax').value || totals.getSegment('dr_ior_tax').value === '0.0000') {
                    if(totals.getSegment('dr_ior').value){
                        return this.getFormattedPrice(0);
                    }else {
                        return 0;
                    }
                } else {
                    var price = totals.getSegment('dr_ior_tax').value;
                    return this.getFormattedPrice(price);
                }
            }
        });
    }
);
