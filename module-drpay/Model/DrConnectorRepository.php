<?php

/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Model;

use Digitalriver\DrPay\Model\DrConnectorFactory as ResourceDrConnector;
use Magento\Framework\Json\Helper\Data as JsonHelperData;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Model\OrderFactory;
use \Magento\Sales\Model\Order as Order;
use \Digitalriver\DrPay\Helper\Data as DrPayData;
use \Digitalriver\DrPay\Helper\Config as DrConfig;
use \Digitalriver\DrPay\Logger\Logger as Logger;

/**
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DrConnectorRepository extends \Magento\Framework\Model\AbstractModel
{

    const ORDER_ACCEPTED = 'order.accepted';
    const ORDER_COMPLETE = 'order.complete';
    const ORDER_BLOCKED = 'order.blocked';
    const REFUND_FAILED = 'refund.failed';
    const ORDER_REVIEW_OPENED = 'order.review_opened';

    const HTTP_OK = 200;
    const HTTP_FAILED = 400;

    /**
     * @var ResourceDrConnector
     */
    protected $resource;

    /**
     *
     * @var type
     */
    protected $orderFactory;

    /**
     *
     * @var type
     */
    protected $jsonHelper;

    /**
     *
     * @param \Digitalriver\DrConnector\Model\DrConnectorFactory $resource
     * @param \Magento\Framework\Json\Helper\Data                $jsonHelper
     * @param \Magento\Sales\Model\OrderFactory                  $orderFactory
     */
    public function __construct(
        ResourceDrConnector $resource,
        JsonHelperData $jsonHelper,
        OrderFactory $orderFactory,
        DrPayData $helper,
        Logger $logger,
        DrConfig $config
    ) {
        $this->orderFactory = $orderFactory;
        $this->resource = $resource;
        $this->jsonHelper = $jsonHelper;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->_config = $config;
    }

    /**
     * DRAPI Event handler
     */
    public function saveEventRequest($payload)
    {
        try {
            $data = json_decode($payload, true);
            if (isset($data['type'])) {
                switch ($data['type']) {
                    case self::ORDER_ACCEPTED:
                        $response = $this->setOrderStatus($data, self::ORDER_ACCEPTED);
                        $this->logger->info(json_encode($response));
                        break;
                    case self::ORDER_COMPLETE:
                        $response = $this->saveOrderCompleteRequest($data);
                        $this->logger->info(json_encode($response));
                        break;
                    case self::ORDER_BLOCKED:
                        $response = $this->setOrderStatus($data, self::ORDER_BLOCKED);
                        $this->logger->info(json_encode($response));
                        break;
                    case self::REFUND_FAILED:
                        $response = $this->saveRefundFailedRequest($data);
                        $this->logger->info(json_encode($response));
                        break;
                    case self::ORDER_REVIEW_OPENED:
                        $response = $this->saveOrderReviewRequest($data);
                        $this->logger->info(json_encode($response));
                        break;
                    default:
                        $response = $this->getDefaultResponse();
                }
            }
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $response;
    }

    private function getDefaultResponse()
    {
        return ['success' => true,
        'message' => 'No action taken',
        'statusCode' => self::HTTP_OK];
    }

    private function saveRefundFailedRequest($data)
    {
        $response = ['success' => false,
        'message' => 'Request failed',
        'statusCode' => self::HTTP_FAILED];
        try {
            $requestObj = $data['data']['object'];
            $requisition_id = $requestObj['orderId'];

            if ($requisition_id) {
                $order = $this->orderFactory->create()->load($requisition_id, 'dr_order_id');
                if ($order->getId()) {
                    $this->logger->info("ORDER IN " . $order->getStatus() . " STATE FOR " .  $requisition_id);
                    //update order status to processing as OFI means payment received
                    $order->addStatusHistoryComment(__('Refund failed for ' . $requestObj['id']));
                    $order->save();
                }
                $response = ['success' => true,
                'message' => 'Request successfully processed',
                'statusCode' => self::HTTP_OK];
            }
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $response;
    }

    private function setOrderStatus($data, $webhook)
    {
        $comment = '';
        $state = '';
        switch ($webhook) {
            case 'order.accepted':
                $comment = 'Order accepted';
                $state = Order::STATE_PROCESSING;
                break;
            case 'order.blocked':
                $comment = 'Suspected fraud';
                $state = Order::STATUS_FRAUD;
                break;
        }
        $response = ['success' => false,
        'message' => 'Request failed',
        'statusCode' => self::HTTP_FAILED];
        try {
            $requestObj = $data['data']['object'];
            $requisition_id = $requestObj['id'];
            if ($requisition_id) {
                $order = $this->orderFactory->create()->load($requisition_id, 'dr_order_id');
                if ($order->getId() && in_array(
                    $order->getStatus(),
                    [\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
                    \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW]
                )
                ) {
                    $this->logger->info("ORDER IN " . $order->getStatus() . " STATE FOR " .  $requisition_id);
                    //update order status to processing as OFI means payment received
                    $order->setDrOrderState($requestObj['state']);
                    $order->setState($state);
                    $order->setStatus($state);
                    $order->addStatusHistoryComment(__($comment));
                    $order->save();
                    $this->logger->info("ORDER UPDATED TO " . $order->getStatus() . " STATE FOR " .  $requisition_id);
                    $response = ['success' => true,
                    'message' => 'Request successfully processed',
                    'statusCode' => self::HTTP_OK];
                }
            }
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $response;
    }

    private function saveOrderCompleteRequest($data)
    {
        try {
            $requestObj = $data['data']['object'];
            $requisition_id = $requestObj['id'];

            if ($requisition_id) {
                $order = $this->orderFactory->create()->load($requisition_id, 'dr_order_id');
                if ($order->getId() && in_array(
                    $order->getStatus(),
                    [\Magento\Sales\Model\Order::STATE_PROCESSING,
                    \Magento\Sales\Model\Order::STATE_COMPLETE]
                )
                ) {
                    //update order status to processing as OFI means payment received
                    $this->logger->info("ORDER IN " . $order->getStatus() . " STATE FOR " .  $requisition_id);
                    $order->setDrOrderState($requestObj['state']);
                    $canInvoice = $order->canInvoice();
                    if (!$canInvoice) {
                        $order->setState(Order::STATE_COMPLETE);
                        $order->setStatus(Order::STATE_COMPLETE);
                    }
                    $order->addStatusHistoryComment(__('Order complete'));
                    $order->save();

                    $model = $this->resource->create();
                    $model->load($order->getDrOrderId(), 'requisition_id');
                    if ($model->getId()) {
                        $model->setPostStatus(1);
                        $model->save();
                    }
                }
                $response = ['success' => true,
                'message' => 'Request successfully processed',
                'statusCode' => self::HTTP_OK];
            } else {
                $response = ['success' => false,
                'message' => 'Request failed',
                'statusCode' => self::HTTP_FAILED];
            }
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $response;
    }

    private function saveOrderReviewRequest($data)
    {
        $response = ['success' => false,'message' => 'Request failed', 'statusCode' => self::HTTP_FAILED];
        try {
            $requestObj = $data['data']['object'];
            $requisition_id = $requestObj['id'];
            $line_item_ids = $requestObj['items'];

            if ($requisition_id) {

                $order = $this->orderFactory->create()->load($requisition_id, 'dr_order_id');
                if ($order->getId() && in_array(
                    $order->getStatus(),
                    [\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT]
                )
                ) {
                    $this->logger->info("ORDER IN " . $order->getStatus() . " STATE FOR " .  $requisition_id);
                    //update order status to processing as OFI means payment received
                    $order->setDrOrderState($requestObj['state']);
                    $order->setState(Order::STATE_PAYMENT_REVIEW);
                    $order->setStatus(Order::STATE_PAYMENT_REVIEW);
                    $order->addStatusHistoryComment(__('Payment review'));
                    $order->save();
                }
                $response = ['success' => true,
                'message' => 'The request has been successfully processed by Magento',
                'statusCode' => self::HTTP_OK];
            } else {
                $response = ['success' => false,
                'message' => 'Failed to updated in Magento',
                'statusCode' => self::HTTP_OK];
            }
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $response;
    }

    /**
     * COMAPI Save fulfillment
     */
    public function saveFulFillment($OrderLevelElectronicFulfillmentRequest)
    {
        $response = [];
        $lineItemIds = [];
        $electronicFulfillmentNotices = [(object) []];
        $requisitionId = $OrderLevelElectronicFulfillmentRequest['requisitionID'];
        $lineItemsIds = $OrderLevelElectronicFulfillmentRequest['lineItemLevelRequest'];
        $requestObj = $this->jsonHelper->jsonEncode($OrderLevelElectronicFulfillmentRequest);
        // Getting lineItemids
        if (is_array($lineItemsIds) && isset($lineItemsIds['quantity'])) {
            $lineItemIds[] = ['qty' => $lineItemsIds['quantity'],'lineitemid'=>$lineItemsIds['lineItemID']];
        } else {
            foreach ($lineItemsIds as $lineItemid) {
                if (is_array($lineItemid)) {
                      $lineItemIds[] = ['qty' => $lineItemid['quantity'],'lineitemid'=>$lineItemid['lineItemID']];
                }
            }
        }
        $data = [ 'requisition_id' => $requisitionId,
        'request_obj' => $requestObj,
        'line_item_ids'=> $this->jsonHelper->jsonEncode($lineItemIds)];
        try {
            if ($requisitionId) {
                $order = $this->orderFactory->create()->load($requisitionId, 'dr_order_id');
                if ($order->getId() && $order->getStatus() != \Magento\Sales\Model\Order::STATE_CANCELED) {
                    $model = $this->resource->create();
                    $model->load($order->getDrOrderId(), 'requisition_id');
                    if (!$model->getId() || $order->getDrOrderState() != "Submitted") {
                        if ($order->getDrOrderState() != "Submitted") {
                            //update order status to processing as OFI means payment received
                            $order->setDrOrderState("Submitted");
                            $order->setState(Order::STATE_PROCESSING);
                            $order->setStatus(Order::STATE_PROCESSING);
                            $order->save();
                        }
                        $model->setData($data);
                        $model->save();
                        $response = ['ElectronicFulfillmentResponse' => [
                                "responseMessage" => "The request has been successfully processed by Magento",
                                "successful" => "true",
                                "isAutoRetriable" => "false",
                                "electronicFulfillmentNotices" => $electronicFulfillmentNotices
                            ]
                        ];
                    } else {
                        $response = ['ElectronicFulfillmentResponse' => [
                                "responseMessage" => "The request has already saved in Magento",
                                "successful" => "false",
                                "isAutoRetriable" => "false",
                                "electronicFulfillmentNotices" => $electronicFulfillmentNotices
                            ]
                        ];
                    }
                }
            } else {
                $response = ['ElectronicFulfillmentResponse' => [
                        "responseMessage" => "Please Provide the requisitionID.",
                        "successful" => "false",
                        "isAutoRetriable" => "false",
                        "electronicFulfillmentNotices" => $electronicFulfillmentNotices
                    ]
                ];
            }
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $response;
    }
}
