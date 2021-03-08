/**
 *
 * Control to render label in Cart/Checkout for IOR Tax item
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
define(
    [
        'Digitalriver_DrPay/js/view/checkout/summary/ior_tax_value',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals'
    ],
    function (Component, quote, totals, iorTaxValue) {
        'use strict';
        return Component.extend({
            /**
             * @override
             */
            totals: quote.getTotals(),isDisplayedIORTaxTotal: function () {
                if (!totals.getSegment('dr_ior').value) {
                    return false;
                } else {
                    return true;
                }
            }
        });
    }
);
