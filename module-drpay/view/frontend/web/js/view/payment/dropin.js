/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
/*browser:true*/
/*global define*/

define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        var config = window.checkoutConfig.payment,
            drDropIn = 'drpay_dropin';
        if (config[drDropIn].is_active) {
            rendererList.push(
                {
                    type: drDropIn,
                    component: 'Digitalriver_DrPay/js/view/payment/method-renderer/dropin'
                }
            );
        }

        /**
    * Add view logic here if needed 
    */
        return Component.extend({});
    }
);
