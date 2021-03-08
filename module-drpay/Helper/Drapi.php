<?php
/**
 * DrApi Helper
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Helper;

use Digitalriver\DrPay\Logger\Logger;
use Digitalriver\DrPay\Model\DrConnectorFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\Url\EncoderInterface;

/**
 * DR API helper library
 */
class Drapi extends AbstractHelper
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var DrConnectorFactory
     */
    private $drFactory;

    /**
     * @var Data
     */
    private $jsonHelper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context            $context
     * @param Session            $session    ,
     * @param DrConnectorFactory $drFactory  ,
     * @param Data               $jsonHelper ,
     * @param Logger             $logger     ,
     * @param Config             $config
     * @param EncoderInterface   $urlEncoder
     */
    public function __construct(
        Context $context,
        Session $session,
        DrConnectorFactory $drFactory,
        Data $jsonHelper,
        Logger $logger,
        Config $config,
        EncoderInterface $urlEncoder
    ) {
        $this->session = $session;
        $this->jsonHelper = $jsonHelper;
        $this->drFactory = $drFactory;
        $this->_logger = $logger;
        $this->config = $config;
        $this->urlEncoder = $urlEncoder;
        parent::__construct($context);
    }

    public function setSku($skuId, $data)
    {
        return $this->config->doCurlPut('skus/' . urlencode($skuId), $data);
    }

    public function getSku($skuId)
    {
        return $this->config->doCurlGet('skus', urlencode($skuId));
    }

    public function getSkus($search)
    {
        return $this->config->doCurlList('skus', $search);
    }

    /**
     * Get source details
     *
     * @param  string $sourceId
     * @return array $result
     */
    public function getSourceDetails($sourceId)
    {
        return $this->config->doCurlGet('sources', $sourceId);
    }
    /**
     * Create customer
     *
     * @param  array $data
     * @return array $result
     */
    public function setCustomer($data)
    {
        return $this->config->doCurlPost('customers', $data);
    }
    /**
     * Create checkout
     *
     * @param  array $data
     * @return array $response
     */
    public function setCheckout($data)
    {
        $response = false;
        $checksum = sha1(json_encode($data));
        $existingChecksum = $this->session->getSessionCheckSum();
        if (!empty($existingChecksum) && $checksum == $existingChecksum) {
            $drresult = $this->session->getDrResult();
            if ($drresult) {
                $result = json_decode($drresult, true);
                return $result;
            }
        }
        $this->session->setSessionCheckSum($checksum);
        $result = $this->config->doCurlPost('checkouts', $data);

        if (!$result['success']) {
            $this->session->setDrResult(json_encode($result));
            return $result;
        }

        $shippingTax = 0;
        $productTax = 0;
        $productTaxRate = 0;
        $productTotalExclTax = 0;
        $tax_inclusive = $this->config->isTaxInclusive();

        $resultNew = $result['message'];
        if (isset($resultNew['items'])) {
            foreach ($resultNew['items'] as $item) {
                $productTax += $item['tax']['amount'];
                if ($tax_inclusive) {
                    if (isset($item['tax']['rate'])
                        && is_numeric($item['tax']['rate'])
                        && isset($item['metadata']['productPriceSubTotalInclTax'])) {
                        $productTotalExclTax +=
                            $item['metadata']['productPriceSubTotalInclTax'] / (1 + $item['tax']['rate']);
                        $productTaxRate = $item['tax']['rate'];
                    }
                } else {
                    $productTotalExclTax += $item['metadata']['productPriceSubTotal'];
                }
            }
        }
        $response['productTotalExclTax'] = $this->config->round($productTotalExclTax);
        $response['productTax'] = $this->config->round($productTax);
        $response['shippingTax'] =  0;
        $response['shippingTotalExclTax'] = 0;
        $response['success'] = $result['success'];
        $result = $result['message'];
        $response['id'] = $result['id'];

        if (isset($result['shippingChoice'])) {
            $response['shippingTax'] = $result['shippingChoice']['taxAmount'];
            if ($tax_inclusive && $result['metadata']['shippingDiscount']) {
                $response['shippingTotalExclTax'] = $result['metadata']['shippingAmount'] / (1 + $productTaxRate);
            } else {
                $response['shippingTotalExclTax'] = $result['shippingChoice']['amount'];
            }
        }
        $response['orderTotal'] = $this->config->round($result['totalAmount']);
        $response['orderTax'] = $this->config->round($result['totalTax']);
        $response['shippingTotalExclTax'] = $this->config->round($response['shippingTotalExclTax']);
        $response['shippingTax'] = $this->config->round($response['shippingTax']);

        if (isset($result['importerOfRecordTax']) && $result['importerOfRecordTax'] === true) {
            $response['importerOfRecordTax'] = $result['importerOfRecordTax'];
            $response['totalDuty'] = $this->config->round($result['totalDuty']);
        } else {
            $result['importerOfRecordTax'] = 0;
        }

        $response['paymentSessionId'] = $result['paymentSessionId'];
        $response['sellingEntity'] = isset($result['sellingEntity']) ?
            $result['sellingEntity']['id'] : $this->config->getDefaultSellingEntity();
        $this->session->setDrResult(json_encode($response));
        return $response;
    }

    /**
     * @param  mixed  $sourceId
     * @param  string $name
     * @return mixed|null
     */
    public function setCustomerSource($sourceId)
    {
        $result = $this->config->doCurlPost(
            'customers/' . $this->session->getDrCustomerId() . '/sources/' . $sourceId,
            []
        );
        return $result;
    }

    public function getCustomer($customerId)
    {
        $result = $this->config->doCurlGet('customers', $customerId);
        return $result;
    }

    /**
     * @param  mixed $data
     * @return mixed|null
     */
    public function setCheckoutUpdate($checkoutId, $data)
    {
        return $this->config->doCurlPost('checkouts/' . $checkoutId, $data);
    }
    /**
     * @param  mixed $accessToken
     * @return mixed|null
     */
    public function setOrder($checkoutId)
    {
        $data['checkoutId'] = $checkoutId;
        return $this->config->doCurlPost('orders', $data);
    }

    /**
     *
     * @return type
     */
    public function setOrderStateComplete($order)
    {
        $request['orderId'] = $order->getDrOrderId();
        $drConnector = $this->drFactory->create();

        $drObj = $drConnector->load($order->getDrOrderId(), 'requisition_id');

        if ($drObj->getId()) {
            $lineItems = $this->jsonHelper->jsonDecode($drObj->getLineItemIds());
            foreach ($lineItems as $item) {
                $dataItem = ['skuId' => $item['sku'], 'quantity' => $item['qty']];
                $request['items'][] = $dataItem;
            }
            $result = $this->config->doCurlPost('fulfillments', $request);
        }
        return ['success' => false];
    }

    public function getFulfillmentRequest($order)
    {
        $request['orderId'] = $order->getDrOrderId();
        return $this->config->doCurlList('fulfillments', $request);
    } // end: function

    /**
     * Function to send EFN request to DR when Invoice/Shipment created from Magento Admin
     * Only Invoice/Shipment Success cases are sent
     *
     * @param array  $lineItems
     * @param object $order
     *
     * @return array $result
     */
    public function setFulfillmentRequest($lineitems, $order)
    {
        $request['orderId'] = $order->getDrOrderId();
        foreach ($lineitems as $itemId => $item) {
            $dataItem = ['skuId' => $item['sku'], 'quantity' => $item['quantity']];
            $request['items'][] = $dataItem;
        }
        return $this->config->doCurlPost('fulfillments', $request);
    } // end: function

    public function setOrderCancellation($order)
    {
        $request['orderId'] = $order['id'];
        foreach ($order['items'] as $item) {
            $dataItem = ['skuId' => $item['skuId'], 'cancelQuantity' => $item['quantity']];
            $request['items'][] = $dataItem;
        }
        return $this->config->doCurlPost('fulfillments', $request);
    }

    /**
     * Function to send a fulfillment cancel request to DR when @OrderItem is cancelled from Magento Admin
     *
     * @param array  $lineItems
     * @param object $order
     *
     * @return array $result
     */
    public function setFulfillmentCancellation($lineitems, $order)
    {
        $request['orderId'] = $order->getDrOrderId();
        foreach ($lineitems as $itemId => $item) {
            $dataItem = ['skuId' => $item['sku'], 'cancelQuantity' => $item['quantity']];
            $request['items'][] = $dataItem;
        }
        return $this->config->doCurlPost('fulfillments', $request);
    }

    private function getRefundByOrderId($orderId)
    {
        $result = $this->config->doCurlList('refunds', ['orderId' => $orderId]);
        if (isset($result['message']['data']) && !empty($result['message']['data'])) {
            $existingData = [];
            $totalRefunded = 0;
            /// determine amounts returned for each lineitem and shipping
            foreach ($result['message']['data'] as $existingRefundDataItem) {
                if (!empty($existingRefundDataItem['items'])) {
                    foreach ($existingRefundDataItem['items'] as $item) {
                        isset($existingData[$existingRefundDataItem['metadata']['orderItemId']])
                        || $existingData[$existingRefundDataItem['metadata']['orderItemId']] = 0;
                        $existingData[$existingRefundDataItem['metadata']['orderItemId']] += $item['amount'];
                        $totalRefunded += $item['amount'];
                    }
                } elseif ($existingRefundDataItem['type'] == 'shipping') {
                    isset($existingData['shipping']) || $existingData['shipping'] = 0;
                    $existingData['shipping'] +=  $existingRefundDataItem['amount'];
                    $totalRefunded += $existingRefundDataItem['amount'];
                } elseif (isset($existingRefundDataItem['metadata'])
                    && isset($existingRefundDataItem['metadata']['adjustment'])) {
                    isset($existingData['adjustment']) || $existingData['adjustment'] = 0;
                    $existingData['adjustment'] += $existingRefundDataItem['amount'];
                    $totalRefunded += $existingRefundDataItem['amount'];
                }
            }
            $existingData['totalRefunded'] = $totalRefunded;
            return $existingData;
        }
        return false;
    }

    /**
     *
     * @return type
     */
    public function setRefundRequest($creditmemo)
    {
        $order = $creditmemo->getOrder();
        $storeCode = $order->getStore()->getCode();
        $orderId = $order->getDrOrderId();
        $currencyCode = $order->getOrderCurrencyCode();

        $creditmemoItems = $creditmemo->getAllItems();

        $refundData = [];

        foreach ($creditmemoItems as $item) {
            $this->_logger->info("CREDIT MEMO ITEM " . json_encode($item->toArray()));
            $data = [];
            $data['orderId'] = $orderId;
            $data['currency'] = $currencyCode;
            $data['reason'] = 'CUSTOMER_SATISFACTION_ISSUE';
            $items['skuId'] = $item->getSku();
            $items['amount'] = ($item->getRowTotalInclTax() - $item->getDiscountAmount()) / $item->getQty();
            $items['quantity'] = $item->getQty();
            $data['items'][] = $items;
            $data['metadata']['magentoOrderId'] = $order->getEntityId();
            $data['metadata']['magentoOrderItemId'] = $item->getOrderItemId();
            $data['metadata']['subTotal'] = $item->getRowTotalInclTax();
            $data['metadata']['subTotalDiscount'] = $item->getDiscountAmount();
            $data['metadata']['type'] = 'product';
            $refundData[] = $data;
        }

        if ($creditmemo->getAdjustmentPositive()) {
            $data = [];
            $data['orderId'] = $orderId;
            $data['currency'] = $currencyCode;
            $data['amount'] = $creditmemo->getAdjustmentPositive();
            $data['reason'] = 'CUSTOMER_SATISFACTION_ISSUE';
            $data['metadata']['magentoOrderId'] = $order->getEntityId();
            $data['metadata']['type'] = 'adjustment';
            $refundData[] = $data;
        }

        if ($creditmemo->getShippingInclTax()) {
            $data = [];
            $data['orderId'] = $orderId;
            $data['currency'] = $currencyCode;
            $data['type'] = 'shipping';
            $data['amount'] = $creditmemo->getShippingInclTax() - $creditmemo->getShippingDiscountAmount();
            $data['reason'] = 'CUSTOMER_SATISFACTION_ISSUE';
            $data['metadata']['magentoOrderId'] = $order->getEntityId();
            $data['metadata']['shippingInclTax'] = $creditmemo->getShippingInclTax();
            $data['metadata']['shippingDiscount'] = $creditmemo->getShippingDiscountAmount();
            $data['metadata']['type'] = 'shipping';
            $refundData[] = $data;
        }

        if ($creditmemo->getData('dr_duty_fee')) {
            $data = [];
            $data['orderId'] = $orderId;
            $data['currency'] = $currencyCode;
            $data['type'] = 'duty';
            $data['amount'] = $creditmemo->getData('dr_duty_fee');
            $data['reason'] = 'REQUESTED_BY_CUSTOMER';
            $refundData[] = $data;
        }

        if ($creditmemo->getData('dr_ior_tax')) {
            $data = [];
            $data['orderId'] = $orderId;
            $data['currency'] = $currencyCode;
            $data['type'] = 'tax';
            $data['amount'] = $creditmemo->getData('dr_ior_tax');
            $data['reason'] = 'REQUESTED_BY_CUSTOMER';
            $refundData[] = $data;
        }

        $this->_logger->info("DR REFUND DATA " . json_encode($refundData));
        foreach ($refundData as $data) {
            // send the request to DR
            $result = $this->config->doCurlPost('refunds', $data);
            if (!$result['success']) {
                return false;
            }
        }
        return true;
    }
}
