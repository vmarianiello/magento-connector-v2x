<?php
/**
 * DrPay Observer
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order as Order;

/**
 * Update Digitalriver order details
 *
 */
class UpdateOrderDetails implements ObserverInterface
{
    /**
     * @param \Digitalriver\DrPay\Helper\Data            $helper
     * @param \Magento\Checkout\Model\Session            $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $helper,
        \Digitalriver\DrPay\Helper\Config $config,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Model\Order $order,
        \Digitalriver\DrPay\Model\DrConnector $drconnector,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        $this->helper = $helper;
        $this->config = $config;
        $this->session = $session;
        $this->order = $order;
        $this->drconnector = $drconnector;
        $this->jsonHelper = $jsonHelper;
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
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        $result = $observer->getEvent()->getResult();

        if (isset($result["id"])) {
            $orderId = $result["id"];
            $order->setDrOrderId($orderId);
            $order->setDrApiType('drapi');
            $order->setDrPaymentMethod($result['dr_payment_method']);
            // all orders are to be put in a pending payment state
            //$order->setDrOrderState($result['state']);
            $amount = $result['totalTax'];
            if (isset($result['importerOfRecordTax']) && $result['importerOfRecordTax']) {
                $amount = 0;
            }
            $tax_inclusive = $this->config->isTaxInclusive();

            $order->setDrTax($amount);
            $order->setTaxAmount($amount);
            $order->setBaseTaxAmount($this->config->convertToBaseCurrency($amount));

            if (isset($result['items'])) {
                $lineItems = $result['items'];
                $model = $this->drconnector->load($orderId, 'requisition_id');
                $model->setRequisitionId($orderId);
                $lineItemIds = [];
                foreach ($lineItems as $item) {
                    $lineItemIds[] = ['qty' => $item['quantity'],'lineitemid' => $item['id'], 'sku' => $item['skuId']];
                }
                $model->setLineItemIds($this->jsonHelper->jsonEncode($lineItemIds));
                $model->save();
                $subTotalExclTax = 0;
                $subTotalInclTax = 0;
                $subTotalTax = 0;
                foreach ($order->getAllItems() as $orderitem) {
                    // get the magento lineitem quote ID
                    $magentoItemId = $orderitem->getQuoteItemId();

                    foreach ($lineItems as $item) {
                        // loop thru the item's custom attributes to extract the magento_quote_item_id,
                        // productPriceExclTax, productPriceSubTotalInclTax, productPriceSubTotalExclTax

                        $drItemMagentoRefId = $item['metadata']['magento_quote_item_id'];
                        $metadata = $item['metadata'];
                        unset($item['metadata']);
                        $item = array_merge($item, $metadata);// phpcs:ignore Magento2.Performance.ForeachArrayMerge

                        // if the DR cart's magento_quote_item_id == magentoItemId, then update the Items details
                        if ($drItemMagentoRefId == $magentoItemId) {
                            $this->updateDrItemsDetails($orderitem, $item, $tax_inclusive);
                            $subTotalExclTax += $orderitem->getRowTotal();
                            $subTotalInclTax += $orderitem->getRowTotalInclTax();
                            $subTotalTax += $orderitem->getTaxAmount();
                            break;
                        }
                    }
                }
            }
            // required for MOM
            $shippingTax = (isset($result['shippingChoice'])) ? $result['shippingChoice']['taxAmount'] : 0;

            $order->setShippingTaxAmount($this->config->round($shippingTax));
            $order->setBaseShippingTaxAmount($this->config->convertToBaseCurrency($shippingTax));
            $order->setShippingAmount($this->config->round($order->getShippingAmount()));
            $order->setBaseShippingAmount($this->config->convertToBaseCurrency($order->getShippingAmount()));
            if ($tax_inclusive) {
                $order->setSubtotal($this->config->round($subTotalExclTax));
                $order->setBaseSubtotal($this->config->convertToBaseCurrency($order->getSubtotal()));
                $order->setSubtotalInclTax($this->config->round($subTotalInclTax));
                $order->setBaseSubtotalInclTax($this->config->convertToBaseCurrency($order->getSubtotalInclTax()));

                // set subtotal discount compensation amount
                $subTotalCompensation = 0;
                if ($result['metadata']['subTotalDiscount'] > 0) {
                    $inclVal = $subTotalInclTax - $result['metadata']['subTotalDiscount'];
                    $exclVal = $subTotalExclTax + $subTotalTax - $result['metadata']['subTotalDiscount'];
                    $subTotalCompensation = $inclVal - $exclVal;
                }

                $order->setDiscountTaxCompensationAmount($this->config->round($subTotalCompensation));
                $order->setBaseDiscountTaxCompensationAmount(
                    $this->config->convertToBaseCurrency($subTotalCompensation)
                );

                $shippingCompensation = 0;
                if ($result['metadata']['shippingDiscount'] > 0) {
                    $shippingInclTax = $order->getShippingInclTax();
                    $shippingExclTax = $order->getShippingAmount();
                    $shippingDiscount = $order->getShippingDiscountAmount();
                    $shippingTaxAmount = $order->getShippingTaxAmount();
                    $inclVal = $shippingInclTax - $shippingDiscount;
                    $exclVal = $shippingExclTax + $shippingTaxAmount - $shippingDiscount;
                    $shippingCompensation = $inclVal - $exclVal;
                }
                // set the shipping tax compensation amount
                $order->setShippingDiscountTaxCompensationAmount($this->config->round($shippingCompensation));
                $order->setBaseShippingDiscountTaxCompensationAmnt(
                    $this->config->convertToBaseCurrency($shippingCompensation)
                );
            } else {
                $order->setSubtotalInclTax($this->config->round($order->getSubtotal() + $result['totalTax']));
                $order->setBaseSubtotalInclTax($this->config->convertToBaseCurrency($order->getSubtotalInclTax()));
            }
            $order->save();
            $this->config->clearSessionData();
        }
    }

    public function updateDrItemsDetails($orderitem, $item, $tax_inclusive)
    {
        $orderitem->setDrOrderLineitemId($item['id']);

        $orderitem->setTaxAmount($this->config->round($item['tax']['amount']));
        $orderitem->setBaseTaxAmount($this->config->convertToBaseCurrency($orderitem->getTaxAmount()));
        $orderitem->setTaxPercent($item['tax']['rate'] * 100);

        if ($tax_inclusive) {
            $subTotalInclTax = $item['productPriceSubTotalInclTax'];
            $subTotalExclTax = $item['productPriceSubTotalInclTax'] / (1 + $item['tax']['rate']);
            $productPriceInclTax = $subTotalInclTax / $item['quantity'];
            $productPriceExclTax = $subTotalExclTax / $item['quantity'];

            // determine the adjusted compensation based on the response from GC
            $compensation = 0;
            if ($item['productDiscount'] > 0) {
                $inclVal = $subTotalInclTax - $item['productDiscount'];
                $exclVal = $subTotalExclTax + $item['tax']['amount'] - $item['productDiscount'];
                $compensation = $inclVal - $exclVal;
            }
            $orderitem->setPrice($this->config->round($productPriceExclTax));
            $orderitem->setBasePrice($this->config->convertToBaseCurrency($orderitem->getPrice()));

            $orderitem->setPriceInclTax($this->config->round($productPriceInclTax));
            $orderitem->setBasePriceInclTax($this->config->convertToBaseCurrency($orderitem->getPriceInclTax()));

            $orderitem->setRowTotal($this->config->round($subTotalExclTax));
            $orderitem->setBaseRowTotal($this->config->convertToBaseCurrency($orderitem->getRowTotal()));

            $orderitem->setRowTotalInclTax($this->config->round($subTotalInclTax));
            $orderitem->setBaseRowTotalInclTax($this->config->convertToBaseCurrency($orderitem->getRowTotalInclTax()));

            // compensation is required to adjust the Row Total column
            // in the order details lineitems based on the GC calculations.
            $orderitem->setDiscountTaxCompensationAmount($this->config->round($compensation));
            $orderitem->setBaseDiscountTaxCompensationAmount($this->config->convertToBaseCurrency($compensation));
        }
    }
}
