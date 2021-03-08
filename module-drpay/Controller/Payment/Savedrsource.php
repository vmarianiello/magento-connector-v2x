<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;

/**
 * Dr API Savedrsource controller
 */
class Savedrsource extends \Magento\Framework\App\Action\Action
{

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     * @param \Digitalriver\DrPay\Logger\Logger     $logger
     * @param \Digitalriver\DrPay\Helper\Data       $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Logger\Logger $logger,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Digitalriver\DrPay\Helper\Config $config
    ) {
        $this->helper =  $helper;
        $this->config = $config;
        $this->_checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        parent::__construct($context);
    }
    /**
     * @return mixed|null
     */
    public function execute()
    {
        $responseContent = [
            'success'        => false,
            'content'        => __("Unable to process")
        ];

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $isEnabled = $this->config->getIsEnabled();
        if (!$isEnabled) {
            return $response->setData($responseContent);
        }

        $sourceId = $this->getRequest()->getParam('sourceId');
        $this->_checkoutSession->setDrSourceId($sourceId);
        $readyForStorage = $this->getRequest()->getParam('readyForStorage');
        if (!empty($readyForStorage) && $readyForStorage == true) {
            $this->_checkoutSession->setDrReadyForStorage($readyForStorage);
        }
        $responseContent = [
        'success'        => true,
        'content'        => ''
        ];
        $checkoutId = $this->_checkoutSession->getDrCheckoutId();
        $this->_checkoutSession->setDrLockedInCheckoutId($checkoutId);

        $response->setData($responseContent);
        return $response;
    }
}
