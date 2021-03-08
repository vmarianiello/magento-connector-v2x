/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

define([
    'uiRegistry',
    'Magento_Checkout/js/checkout-data'
], function (registry, checkoutData) {
    'use strict';

    return function (Component) {
        return Component.extend({

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();
            },

            updateAddresses: function () {
                this._super();
                loadDropIn();
            }
        });
    };
});
