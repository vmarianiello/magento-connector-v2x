<?php
/**
 * Plugin for Duty fee and IOR tax For invoice calculation.
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Plugin\Sales\Order\Invoice\Total;

/**
 * Class Ior Duty fee Plugin
 */
class ShippingPlugin
{
    /**
     * Adds IOR tax and Duty fee to totals of Invoice
     *
     * @param \Magento\Sales\Model\Order\Invoice\Total\Shipping $grand
     * @param $result
     * @param $creditmemo
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterCollect(\Magento\Sales\Model\Order\Invoice\Total\Shipping $shipping, $result, $invoice)
    {
        $grandTotal = $invoice->getGrandTotal();
        $baseGrandTotal = $invoice->getBaseGrandTotal();

        $grandTotal += $invoice->getOrder()->getDrDutyFee();
        $baseGrandTotal += $invoice->getOrder()->getBaseDrDutyFee();

        $grandTotal += $invoice->getOrder()->getDrIorTax();
        $baseGrandTotal += $invoice->getOrder()->getBaseDrIorTax();

        $invoice->setDrIorTax($invoice->getOrder()->getDrIorTax());
        $invoice->setDrDutyFee($invoice->getOrder()->getDrDutyFee());

        $invoice->setGrandTotal($grandTotal);
        $invoice->setBaseGrandTotal($baseGrandTotal);

        return $result;
    }
}
