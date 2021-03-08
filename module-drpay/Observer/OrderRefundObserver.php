<?php
/**
 * @category Digitalriver
 * @package: Digitalriver_DrPay
 *
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderRefundObserver implements ObserverInterface
{

    /**
     *
     * @param \Digitalriver\DrPay\Helper\Data $drHelper
     */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper
    ) {
        $this->drHelper = $drHelper;
    }

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $status = $this->drHelper->setRefundRequest($creditmemo);

        if (!$status) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed to save credit memo'));
        }

        return $this;
    }
}
