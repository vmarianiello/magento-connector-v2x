<?php
/**
 * Totals for Duty fee and IOR tax.
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Block\Adminhtml\Order\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;

/**
 * Adminhtml order creditmemo totals block
 */
class Totals extends \Magento\Sales\Block\Adminhtml\Order\Creditmemo\Totals
{
    /**
     * Initialize creditmemo totals array
     *
     * @return $this
     */
    protected function _initTotals(): Totals
    {
        parent::_initTotals();

        if ($this->getSource()->getOrder()->getDrDutyFee() != null &&
            $this->getSource()->getOrder()->getDrIorTax() != null) {
            $this->addTotal(
                new \Magento\Framework\DataObject(
                    [
                        'code' => 'dr_duty_fee',
                        'value' => $this->getSource()->getDrDutyFee(),
                        'base_value' => $this->getSource()->getBaseDrDutyFee(),
                        'label' => __('Duty Fee'),
                    ]
                )
            );
            $this->addTotal(
                new \Magento\Framework\DataObject(
                    [
                        'code' => 'dr_ior_tax',
                        'value' => $this->getSource()->getDrIorTax(),
                        'base_value' => $this->getSource()->getBaseDrIorTax(),
                        'label' => __('IOR Tax'),
                    ]
                )
            );
        }
        return $this;
    }
}
