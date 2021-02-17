<?php
/**
 * COMAPI Helper
 */

namespace Digitalriver\DrPay\Helper;

use Magento\Framework\App\Helper\Context;

/**
 * Class Data
 */
class Comapi extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var session
     */
    protected $session;
    protected $drFactory;
    protected $jsonHelper;

    /**
     * @param Context                                          $context
     * @param \Magento\Checkout\Model\Session                  $session,
     * @param \Digitalriver\DrPay\Model\DrConnectorFactory     $drFactory,
     * @param \Magento\Framework\Json\Helper\Data              $jsonHelper,
     * @param \Magento\Framework\Encryption\EncryptorInterface $enc,
     * @param \Magento\Framework\HTTP\Client\Curl              $curl,
     * @param \Digitalriver\DrPay\Logger\Logger                $logger,
     * @param \Digitalriver\DrPay\Helper\Config                $config
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $session,
        \Digitalriver\DrPay\Model\DrConnectorFactory $drFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Encryption\EncryptorInterface $enc,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Digitalriver\DrPay\Logger\Logger $logger,
        \Digitalriver\DrPay\Helper\Config $config
    ) {
        $this->session = $session;
        $this->jsonHelper = $jsonHelper;
        $this->drFactory = $drFactory;
        $this->_enc = $enc;
        $this->curl = $curl;
        parent::__construct($context);
        $this->_logger = $logger;
        $this->config = $config;
    }

    /**
     *
     * @return type
     */
    public function getDrPostUrl($storecode = null)
    {
        return $this->scopeConfig->getValue(
            'dr_settings/config/dr_post_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
    }

    /**
     *
     * @return type
     */
    public function getDrRefundUrl($storecode = null)
    {
        return $this->scopeConfig->getValue(
            'dr_settings/config/dr_refund_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
    }

    /**
     *
     * @return type
     */
    public function getCompanyId($storecode = null)
    {
        return $this->scopeConfig->getValue(
            'dr_settings/config/company_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
    }

    public function getDrRefundUsername($storecode = null)
    {
        return $this->scopeConfig->getValue(
            'dr_settings/config/dr_refund_username',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
    }

    public function getDrRefundPassword($storecode = null)
    {
        $dr_refund_pass = $this->scopeConfig->getValue(
            'dr_settings/config/dr_refund_password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
        return $this->_enc->decrypt($dr_refund_pass);
    }

    public function getDrRefundAuthUsername($storecode = null)
    {
        return $this->scopeConfig->getValue(
            'dr_settings/config/dr_refund_auth_username',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
    }

    public function getDrRefundAuthPassword($storecode = null)
    {
        $dr_auth_pass = $this->scopeConfig->getValue(
            'dr_settings/config/dr_refund_auth_password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storecode
        );
        return $this->_enc->decrypt($dr_auth_pass);
    }

    private function _getEFNArray($lineItems, $status, $responseCode)
    {
        $storeCode = $order->getStore()->getCode();
        $items = [];
        foreach ($lineItems as $itemId => $item) {
            $items['item'][] = [
                "requisitionID"             => $item['requisitionID'],
                "noticeExternalReferenceID" => $item['noticeExternalReferenceID'],
                "lineItemID"                => $itemId,
                "fulfillmentCompanyID"      => $this->getCompanyId($storeCode),
                "electronicFulfillmentNoticeItems" => [
                    "item" => [
                        [
                            "status"                => $status,
                            "reasonCode"            => $responseCode,
                            "quantity"              => $item['quantity'],
                            "electronicContentType" => "EntitlementDetail",
                            "electronicContent"     => "magentoEventID"
                        ]
                    ]
                ]
            ];
        } // end: foreach
        return $items;
    }

    /**
     * Function to send EFN request to DR when Invoice/Shipment created from Magento Admin
     * Only Invoice/Shipment Success cases are sent
     *
     * @param array  $lineItems
     * @param object $order
     *
     * @return array $result
     */
    public function setFulfillmentRequest($lineItems, $order)
    {
        $status         = 'Completed';
        $responseCode   = 'Success';
        $request    = [];
        $request['ElectronicFulfillmentNoticeArray'] = $this->_getEFNArray($lineItems, $status, $responseCode);

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_TIMEOUT, 40);
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($this->getDrPostUrl($storeCode), $this->jsonHelper->jsonEncode($request));
        $result     = $this->curl->getBody();
        $statusCode = $this->curl->getStatus();
        $success = true;
        $code = '';
        if (isset($result['errors']) || !in_array($statusCode, ['200', '201', '204'])) {
            $success = false;
        }
        $this->_logger->info('setFulfillmentRequest Request : ' . json_encode($request));
        $this->_logger->info('setFulfillmentRequest Response : ' . json_encode($result));
        return ['success' => $success, 'statusCode' => $statusCode, 'code' => $code, 'message' => $result];
    } // end: function

    /**
     * Function to send EFN request to DR when @OrderItem is cancelled from Magento Admin
     *
     * @param array  $lineItems
     * @param object $order
     *
     * @return array $result
     */
    public function setFulfillmentCancellation($lineItems, $order)
    {
        $status         = 'Cancelled';
        $responseCode   = 'Cancelled';

        $storeCode = $order->getStore()->getCode();
        $request    = [];
        $request['ElectronicFulfillmentNoticeArray'] = $this->_getEFNArray($lineItems, $status, $responseCode);

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_TIMEOUT, 40);
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($this->getDrPostUrl($storeCode), $this->jsonHelper->jsonEncode($request));
        $response = $this->curl->getBody();
        $statusCode = $this->curl->getStatus();
        $success = true;
        $code = '';
        if (isset($response['errors']) || !in_array($statusCode, ['200', '201', '204'])) {
            $success = false;
        }
        $result = ['success' => $success, 'statusCode' => $statusCode, 'code' => $code, 'message' => $response];
        $this->_logger->info('setFulfillmentCancellation Request : ' . json_encode($request));
        $this->_logger->info('setFulfillmentCancellation Response : ' . json_encode($result));
        return $result;
    } // end: function

    /**
     *
     * @return type
     */
    public function setOrderStateComplete($order)
    {
        $storeCode = $order->getStore()->getCode();
        $url = $this->getDrPostUrl($storeCode);
        $fulFillmentPost = $this->getFulFillmentPostRequest($order, $storeCode);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_TIMEOUT, 40);
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($url, $fulFillmentPost);
        $result = $this->curl->getBody();
        $statusCode = $this->curl->getStatus();
        $success = true;
        if (isset($response['errors']) || !in_array($statusCode, ['200', '201', '204'])) {
            $success = false;
        }
        return ['success' => $success, 'statusCode' => $statusCode, 'message' => $result];
    }
    /**
     *
     * @param  type $order
     * @return type
     */
    private function getFulFillmentPostRequest($order, $storeCode = null)
    {
        $status = '';
        $responseCode = '';
        switch ($order->getStatus()) {
            case 'complete':
                $status = "Completed";
                $responseCode = "Success";
                break;
            case 'canceled':
                $status = "Cancelled";
                $responseCode = "Cancelled";
                break;
            case 'pending':
                $status = "Pending";
                $responseCode = "Pending";
                break;
        }

        $drConnector = $this->drFactory->create();

        $drObj = $drConnector->load($order->getDrOrderId(), 'requisition_id');
        $items = [];
        if ($drObj->getId()) {
            $lineItems = $this->jsonHelper->jsonDecode($drObj->getLineItemIds());
            foreach ($lineItems as $item) {
                $items['item'][] =
                    ["requisitionID" => $order->getDrOrderId(),
                        "noticeExternalReferenceID" => $order->getIncrementId(),
                        "lineItemID" => $item['lineitemid'],
                        "fulfillmentCompanyID" => $this->getCompanyId($storeCode),
                        "electronicFulfillmentNoticeItems" => [
                            "item" => [
                                [
                                    "status" => $status,
                                    "reasonCode" => $responseCode,
                                    "quantity" => $item['qty'],
                                    "electronicContentType" => "EntitlementDetail",
                                    "electronicContent" => "magentoEventID"
                                ]
                            ]
                        ]
                    ];
            }
        }
        $request['ElectronicFulfillmentNoticeArray'] = $items;
        return $this->jsonHelper->jsonEncode($request);
    }
    /**
     *
     * @return type
     */
    public function setRefundRequest($creditmemo)
    {
        $order = $creditmemo->getOrder();
        $flag = false;

        $storeCode = $order->getStore()->getCode();
        $url = $this->getDrRefundUrl($storeCode) . "orders/" . $order->getDrOrderId() . "/refunds";
        $token = $this->generateRefundToken($storeCode);
        if ($token) {
            $adjustmentRefund = $creditmemo->getAdjustmentPositive();
            $currencyCode = $order->getOrderCurrencyCode();
            if ($adjustmentRefund > 0) {
                $adjustmentRefund = round($adjustmentRefund, 2);
                $data = ["type" => "orderRefund",
                "category" => "ORDER_LEVEL_PRODUCT",
                "reason" => "VENDOR_APPROVED_REFUND",
                "comments" => "Unhappy with the product",
                "refundAmount" => ["currency" => $currencyCode, "value" => $adjustmentRefund]];
                $response = $this->curlRefundRequest($order->getDrOrderId(), $data, $token, $storeCode);
                if (!$response) {
                    return $response;
                }
            } else {
                $items = $creditmemo->getAllItems();
                $itemDiscount = 0;
                $itemsData = [];
                foreach ($items as $item) {
                    $rowTotalInclTax = $item->getRowTotal() +
                        $item->getTaxAmount() +
                        $item->getDiscountTaxCompensationAmount() -
                        $item->getDiscountAmount();
                    $itemDiscount += $item->getDiscountAmount();
                    if ($rowTotalInclTax > 0) {
                        $rowTotalInclTax = round($rowTotalInclTax, 2);
                        $drLineItemId = $item->getOrderItem()->getDrOrderLineitemId();
                        $itemsData[] = ["lineItemId" => $drLineItemId,
                        "refundAmount" => ["value" => $rowTotalInclTax, "currency" => $currencyCode]];
                    }
                }
                if (count($itemsData) > 0) {
                    $data = ["type" => "productRefund",
                    "category" => "PRODUCT_LEVEL_PRODUCT",
                    "reason" => "VENDOR_APPROVED_REFUND",
                    "comments" => "Unhappy with the product",
                    "lineItems" => $itemsData];
                    $response = $this->curlRefundRequest($order->getDrOrderId(), $data, $token, $storeCode);
                    if (!$response) {
                        return $response;
                    }
                }
                $shippingDiscount = abs($creditmemo->getDiscountAmount()) - $itemDiscount;
                if ($creditmemo->getShippingInclTax() > 0) {
                    $shippingAmount = round($creditmemo->getShippingInclTax() - $shippingDiscount, 2);
                    $data = ["type" => "orderRefund",
                    "category" => "ORDER_LEVEL_SHIPPING",
                    "reason" => "VENDOR_APPROVED_REFUND",
                    "comments" => "Unhappy with the product",
                    "refundAmount" => ["currency" => $currencyCode, "value" => $shippingAmount]];
                    $response = $this->curlRefundRequest($order->getDrOrderId(), $data, $token, $storeCode);
                    if (!$response) {
                        return $response;
                    }
                }
            }
            $flag = true;
        }
        return $flag;
    }

    private function curlRefundRequest($drOrderId, $data, $token, $storeCode)
    {
        $flag = true;
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_TIMEOUT, 40);
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->addHeader("x-siteid", $this->getCompanyId($storeCode));
        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $url = $this->getDrRefundUrl($storeCode) . "orders/" . $drOrderId . "/refunds";
        $this->curl->post($url, json_encode($data));
        $this->_logger->info("Refund Request :" . json_encode($data));
        $result = $this->curl->getBody();
        $result = json_decode($result, true);
        if (isset($result['errors']) && count($result['errors'])>0) {
            $this->_logger->error("Refund Error :" . json_encode($result));
            $flag = false;
        }
        return $flag;
    }
    /**
     *
     * @return type
     */
    private function generateRefundToken($storeCode = null)
    {
        $token = '';
        if ($this->getDrRefundUrl($storeCode)
            && $this->getDrRefundUsername($storeCode)
            && $this->getDrRefundPassword($storeCode)
            && $this->getDrRefundAuthUsername($storeCode)
            && $this->getDrRefundAuthPassword($storeCode)
        ) {
            $url = $this->getDrRefundUrl($storeCode) . 'auth';
            $data = ["grant_type" => "password",
                "username" => $this->getDrRefundUsername($storeCode),
                "password" => $this->getDrRefundPassword($storeCode)];
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 40);
            $this->curl->setOption(
                CURLOPT_USERPWD,
                $this->getDrRefundAuthUsername($storeCode) . ":" . $this->getDrRefundAuthPassword($storeCode)
            );
            $this->curl->addHeader("Content-Type", 'application/x-www-form-urlencoded');
            $this->curl->addHeader("x-siteid", $this->getCompanyId($storeCode));
            $this->curl->post($url, http_build_query($data));
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
            $token = '';
            if (isset($result["access_token"])) {
                $token = $result["access_token"];
            }
        }
        return $token;
    }
}
