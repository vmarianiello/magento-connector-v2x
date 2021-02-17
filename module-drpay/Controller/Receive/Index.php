<?php
/**
 * Receive all event data from the DR API webhooks
 */
namespace Digitalriver\DrPay\Controller\Receive;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Dr API Receive controller
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @param \Magento\Framework\App\Action\Context                  $context
     * @param \Digitalriver\DrPay\Model\DrConnectorRepositoryFactory $drConnectorRepositoryFactory
     */

    public function __construct(
        Context $context,
        \Digitalriver\DrPay\Model\DrConnectorRepositoryFactory $drConnectorRepositoryFactory,
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->drConnectorRepositoryFactory = $drConnectorRepositoryFactory;
        $this->_logger = $logger;
        return parent::__construct($context);
    }
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        // verify the signing request
        //$headers = apache_request_headers();
        //$this->_logger->info(json_encode($headers));
        $responseContent = ['success' => false, 'statusCode' => 200];
        $data = $this->getRequest()->getContent();
        $payload = json_decode($data, true);
        if (in_array($payload['type'], ['order.accepted',
        'order.complete',
        'order.blocked',
        'refund.failed',
        'order.review_opened'])) {
            $type = strtoupper($payload['type']);
            $this->_logger->critical("\n\nWEBHOOK TYPE - $type\n\n");
            $this->_logger->critical("WEBHOOK PAYLOAD " . $data);
            $responseContent = $this->drConnectorRepositoryFactory->Create()->saveEventRequest($data);
            $this->_logger->info("WEBHOOK RESPONSE " . json_encode($responseContent));
        }

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode($responseContent['statusCode']);
        return $response;
    }
}
