/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
/*browser:true*/

define(
    [
    'jquery',
    'underscore',
    'Magento_Checkout/js/view/payment/default'
    ], function (
        $,
        _,
        Component		
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Digitalriver_DrPay/payment/dropin',
                    code: 'drpay_dropin'
                },
				redirectAfterPlaceOrder: false,
				/** Redirect to custom controller for payment */
				afterPlaceOrder: function () {
					$.mage.redirect(window.checkoutConfig.payment.drpay_dropin.redirect_url);
					return false;
				},
                /**
                 * Get payment name
                 *
                 * @returns {String}
                 */
                getCode: function () {
                    return this.code;
                },
				
				/**
                 * Get payment title
                 *
                 * @returns {String}
                 */
                getTitle: function () {
                    return window.checkoutConfig.payment.drpay_dropin.title;
                },

                /**
                 * Check if payment is active
                 *
                 * @returns {Boolean}
                 */
                isActive: function () {
                    var active = this.getCode() === this.isChecked();
                    this.active(active);
                    return active;
                },
				placeOrder: function () {
					$.mage.redirect(window.checkoutConfig.payment.drpay_dropin.redirect_url);					
					return false;
				},
                radioInit: function () {
                    $(".payment-methods input:radio:first").prop("checked", true).trigger("click");
                }        
            }
        );
    }
);
