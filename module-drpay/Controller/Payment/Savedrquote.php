<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;

/**
 * Dr API Savedrquote controller
 */
class Savedrquote extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Directory\Model\Region
     */
    protected $regionModel;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context);
    }
    /**
     * @return mixed|null
     */
    public function execute()
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $responseContent = [
            'success'        => false,
            'content'        => __("Unable to process")
        ];

        $paymentSessionId = $this->_checkoutSession->getDrPaymentSessionId();
        if (!$paymentSessionId) {
            return $response->setData($responseContent);
        }
        $savePayment = ($this->_checkoutSession->getDrCustomerId()) ? true : false;
        $sellingEntity = $this->_checkoutSession->getDrSellingEntity();
        $responseContent = [
        'success'        => true,
        'content'        => ['paymentSessionId' => $paymentSessionId,
            'sellingEntity' => $sellingEntity,
            'savePayment' => $savePayment]
        ];

        $response->setData($responseContent);
        return $response;
    }
}
