<?php
/**
 *
 */
namespace Digitalriver\DrPay\Controller\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Context;

/**
 * Dr API success controller
 */
class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
        /**
         * @var \Magento\Quote\Model\QuoteFactory
         */
    protected $quoteFactory;
        /**
         * @var \Magento\Directory\Model\Region
         */
    protected $regionModel;
    /**
     * @var \Digitalriver\DrPay\Logger\Logger
     */
    protected $logger;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session       $customerSession
     * \Magento\Sales\Model\Order $order
     * \Magento\Checkout\Model\Session $checkoutSession
     * \Digitalriver\DrPay\Helper\Data $helper
     * \Magento\Directory\Model\Region $regionModel
     * \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Digitalriver\DrPay\Logger\Logger     $logger
     */

    public function __construct(
        Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Directory\Model\Region $regionModel,
        \Digitalriver\DrPay\Model\DrConnector $drconnector,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->customerSession = $customerSession;
        $this->order = $order;
        $this->helper =  $helper;
        $this->checkoutSession = $checkoutSession;
        $this->quoteFactory = $quoteFactory;
        $this->regionModel = $regionModel;
        $this->drconnector = $drconnector;
        $this->jsonHelper = $jsonHelper;
        $this->quoteManagement = $quoteManagement;
        $this->_logger = $logger;
        return parent::__construct($context);
    }

    /**
     * Payment Success response
     *
     * @return mixed|null
     */
    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getId() && $quote->getIsActive()) {
            try {
                /**
                 * @var \Magento\Framework\Controller\Result\Redirect $resultRedirect
                 */
                $resultRedirect = $this->resultRedirectFactory->create();
                $checkoutId = $this->checkoutSession->getDrLockedInCheckoutId();
                if (empty($checkoutId)) {
                    $this->messageManager->addError(__('Unable to Place Order'));
                    $this->_redirect('checkout/cart');
                    return;
                }
                $sourceId = $this->checkoutSession->getDrSourceId();
                if (empty($sourceId)) {
                    $this->messageManager->addError(__('Unable to Place Order'));
                    $this->_redirect('checkout/cart');
                    return;
                }
                $readyForStorage = $this->checkoutSession->getDrReadyForStorage();
                if ($this->checkoutSession->getDrCustomerId() && $readyForStorage) {
                    $result = $this->helper->setCustomerSource($sourceId);
                    if (!$result['success']) {
                        $this->messageManager->addError(__('Unable to Place Order'));
                        $this->_redirect('checkout/cart');
                        return;
                    }
                }
                $data['sourceId'] = $sourceId;
                $upstreamId = $quote->getReservedOrderId();
                if ($upstreamId) {
                    $data['upstreamId'] = $upstreamId;
                }
                $email = $quote->getBillingAddress()->getEmail();
                if ($email) {
                    $data['email'] = $email;
                }
                if (!$quote->getIsVirtual() && $quote->getShippingAddress()) {
                    $data['shipTo'] = $this->helper->getShipToAddress($quote->getShippingAddress());
                }
                $result = $this->helper->setCheckoutUpdate($checkoutId, $data);
                if (!$result['success']) {
                    $this->messageManager->addError(__('Unable to Place Order'));
                    $this->_redirect('checkout/cart');
                    return;
                }
                $result = $this->helper->setOrder($checkoutId);
                if (!$result['success']) {
                    $this->messageManager->addError(__('Unable to Place Order'));
                    $this->_redirect('checkout/cart');
                    return;
                }
                $result = $result['message'];
                // result should be to return the billing address to Magento

                // "last successful quote"
                $quoteId = $quote->getId();
                $this->checkoutSession->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);
                if (!$quote->getCustomerId()) {
                    $quote->setCustomerId(null)
                        ->setCustomerFirstname($quote->getBillingAddress()->getFirstname())
                        ->setCustomerLastname($quote->getBillingAddress()->getLastname())
                        ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                        ->setCustomerIsGuest(true)
                        ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
                }
                $quote->collectTotals();

                // Check quote has any errors
                $isQuoteValid = $this->helper->isQuoteValid($quote);

                if (!empty($isQuoteValid)) {

                    $source = $this->helper->getSourceDetails($sourceId);
                    // Update Quote's Billing Address details from DR Order creation response
                    $billingAddress = $this->helper->getBillingAddressFromSource($source);
                    if ($billingAddress) {
                        $quote->getBillingAddress()->addData($billingAddress);
                    } // end: if

                    $order = $this->quoteManagement->submit($quote);
                    if ($order) {
                        $this->checkoutSession->setLastOrderId($order->getId())
                            ->setLastRealOrderId($order->getIncrementId())
                            ->setLastOrderStatus($order->getStatus());
                    } else {
                        $this->helper->setOrderCancellation($result);
                        $this->messageManager->addError(__('Unable to Place Order'));
                        $this->_redirect('checkout/cart');
                        return;
                    }

                    $this->helper->logger(json_encode($source));
                    if ($source['success'] && isset($source['message']['wireTransfer'])) {
                        $order->getPayment()->setAdditionalInformation($source['message']['wireTransfer']);
                    }
                    $result['dr_payment_method'] = ucwords($source['message']['type']);

                    $this->_eventManager->dispatch(
                        'dr_place_order_success',
                        ['order' => $order, 'quote' => $quote, 'result' => $result]
                    );
                    $this->_redirect('checkout/onepage/success', ['_secure' => true]);
                    return;
                } else {
                    $this->helper->setOrderCancellation($result);
                    $this->_redirect('checkout/cart');
                    return;
                }
            } catch (\Magento\Framework\Exception\LocalizedException $le) {
                $this->_logger->error('Payment Error : '.json_encode($le->getRawMessage()));
                $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                // If exception thrown from DR calls, then $result may be emtpy which will lead to another error
                if (!empty($result) && is_array($result)) {
                    $this->helper->setOrderCancellation($result);
                } // end: if
                $this->_redirect('checkout/cart');
                return;
            } catch (\Exception $ex) {
                $this->_logger->error('Payment Error : '.json_encode($ex->getMessage()));
                $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                // If exception thrown from DR calls, then $result may be emtpy which will lead to another error
                if (!empty($result) && is_array($result)) {
                    $this->helper->setOrderCancellation($result);
                } // end: if
                $this->_redirect('checkout/cart');
                return;
            } // end: try
        }
        $this->_redirect('checkout/cart');
    }
}
