<?php
/**
 * DrPay Observer
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order as Order;

/**
 * Updates the digital river order status
 *
 */
class UpdateOrderStatus implements ObserverInterface
{
    /**
     * @param \Digitalriver\DrPay\Helper\Data            $helper
     * @param \Magento\Checkout\Model\Session            $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $helper,
        \Digitalriver\DrPay\Helper\Config $config,
        \Magento\Sales\Model\Order $order
    ) {
        $this->helper = $helper;
        $this->config = $config;
        $this->order = $order;
    }

    /**
     * Create order
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderId = $observer->getEvent()->getOrderIds();
        $order = $this->order->load($orderId);
        if ($order->getDrOrderId()) {
            // all orders will be updated via webhooks
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setStatus(Order::STATE_PENDING_PAYMENT);
            /*if($order->getDrOrderState() == "Submitted" || $order->getDrOrderState() == 'accepted'){
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus(Order::STATE_PROCESSING);
            }else if($order->getDrOrderState() == "Source Pending Funds" || $order->getDrOrderState() ==
            "Charge Pending" || $order->getDrOrderState() == 'pending_payment'){
                $order->setState(Order::STATE_PENDING_PAYMENT);
                $order->setStatus(Order::STATE_PENDING_PAYMENT);
            }else{
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->setStatus(Order::STATE_PAYMENT_REVIEW);
            }*/
            $tax_inclusive = $this->config->isTaxInclusive();
            foreach ($order->getAllVisibleItems() as $orderitem) {
                if ($orderitem->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE) {
                    $parent_tax_amount = 0;
                    foreach ($orderitem->getChildrenItems() as $childitem) {
                        $child_tax_amount = $childitem->getPriceInclTax() - $childitem->getPrice();
                        if ($child_tax_amount > 0) {
                            $qty = $childitem->getQtyOrdered();
                            $parent_tax_amount = $parent_tax_amount + ($child_tax_amount * $qty);
                        }
                    }
                    if ($parent_tax_amount > 0) {
                        $qty = $orderitem->getQtyOrdered();
                        $total_tax_amount = $parent_tax_amount * $qty;
                        $orderitem->setTaxAmount($this->config->round($total_tax_amount));
                        $orderitem->setBaseTaxAmount($this->config->convertToBaseCurrency($orderitem->getTaxAmount()));
                        if ($tax_inclusive) {
                            $orderitem->setPrice($this->config->round(
                                $orderitem->getPriceInclTax() - $parent_tax_amount
                            ));
                            $orderitem->setBasePrice($this->config->convertToBaseCurrency($orderitem->getPrice()));
                            $orderitem->setRowTotal($this->config->round($orderitem->getPrice() * $qty));
                            $orderitem->setBaseRowTotal($this->config->convertToBaseCurrency(
                                $orderitem->getRowTotal()
                            ));
                        } else {
                            $orderitem->setPriceInclTax($this->config->round(
                                $orderitem->getPrice() + $parent_tax_amount
                            ));
                            $orderitem->setBasePriceInclTax($this->config->convertToBaseCurrency(
                                $orderitem->getPriceInclTax()
                            ));
                            $orderitem->setRowTotalInclTax($this->config->round(
                                $orderitem->getRowTotal() + $total_tax_amount
                            ));
                            $orderitem->setBaseRowTotalInclTax($this->config->convertToBaseCurrency(
                                $orderitem->getRowTotalInclTax()
                            ));
                        }
                    }
                }
            }
            $order->save();
        }
    }
}
