<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Plugin\SalesRule\Model\Quote\Address\Total;

class ShippingDiscount
{
    protected $drHelper;

    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper
    ) {
         $this->drHelper = $drHelper;
    }

    public function afterCollect(
        \Magento\SalesRule\Model\Quote\Address\Total\ShippingDiscount $subject,
        $result,
        $quote,
        $shippingAssignment,
        $total
    ) {
        $address = $shippingAssignment->getShipping()->getAddress();
        if ($address->getAddressType() == 'shipping') {
            //Create the cart in DR
            $this->drHelper->setCheckout($quote);
        }
        return $result;
    }
}
