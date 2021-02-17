<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Model\Total\Quote;

use Digitalriver\DrPay\Helper\Config;
use Digitalriver\DrPay\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class DrTax extends AbstractTotal
{
    public function __construct(
        Session $checkoutSession,
        Data $helper,
        Config $config
    ) {
        $this->setCode('dr_tax');
        $this->_checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->config = $config;
    }

    /**
     * Collect totals process.
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        $address = $shippingAssignment->getShipping()->getAddress();
        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        if ($this->config->getIsEnabled() && $address && $address->getCity()
            && !$this->_checkoutSession->getDrQuoteError()) {
            $tax_inclusive = $this->config->isTaxInclusive();
            $drtax = $this->_checkoutSession->getDrTax();

            $productTotal = $this->_checkoutSession->getDrProductTotal();
            $productTotalExcl = $this->_checkoutSession->getDrProductTotalExcl();
            $orderTotal = $this->_checkoutSession->getDrOrderTotal();

            $shippingAndHandlingExcl = $this->_checkoutSession->getDrShippingAndHandlingExcl();

            if ($tax_inclusive) {
                $total->setShippingAmount($shippingAndHandlingExcl);
            }

            $total->setSubtotalInclTax($productTotal);
            $total->setSubtotal($productTotalExcl);

            $total->setBaseGrandTotal($this->config->convertToBaseCurrency($orderTotal));
            $total->setGrandTotal($orderTotal);

            $total->setBaseTaxAmount($this->config->convertToBaseCurrency($drtax));
            $total->setTaxAmount($drtax);
        }
        return $this;
    }

    /**
     * Fetch (Retrieve data as array)
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     * @internal param \Magento\Quote\Model\Quote\Address $address
     */
    public function fetch(Quote $quote, Total $total)
    {
        $result = null;
        $amount = $quote->getDrTax();
        if ($amount == 0) {
            $billingaddress = $quote->getBillingAddress();
            $amount = $billingaddress->getTaxAmount();
        }
        $result = [
            'code' => $this->getCode(),
            'title' => __('Tax'),
            'value' => $amount
        ];

        return $result;
    }
}
