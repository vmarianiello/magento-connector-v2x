<?php
/**
 *
 */
namespace Digitalriver\DrPay\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Getcards
 */
class Getsavedsources extends \Magento\Framework\App\Action\Action
{
    /**
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Checkout\Model\Session        $checkoutSession
     * @param \Digitalriver\DigitalRiver\Helper\Data $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper
    ) {
        $this->helper =  $helper;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context);
    }
    /**
     * @return mixed|null
     */
    public function execute()
    {
        $responseContent = [
            'success'    => false,
        'content'    => ''
        ];
        if ($this->_checkoutSession->getDrCustomerId()) {
            $result = $this->helper->getSavedSources($this->_checkoutSession->getDrCustomerId());
            if ($result['success']) {
                $responseContent = [
                'success'        => $result['success'],
                'content'        => $result['message']
                ];
            }
        }
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        return $response;
    }
}
