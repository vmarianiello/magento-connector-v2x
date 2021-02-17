<?php declare(strict_types=1);

/**
 * Hide Body Tax amount and Tax Percent column value item, once duty_fee or ior tax available
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Plugin\Block\Adminhtml\Order\View\Items\Renderer;

use Digitalriver\DrPay\Helper\Config;
use Magento\Sales\Block\Adminhtml\Order\View\Items\Renderer\DefaultRenderer as MagentoDefaultRenderer;

/**
 * Hide Header Tax amount and Tax Percent column
 */
class DefaultRenderer
{
    /**
     * @var Config $config
     */
    private $config;

    /**
     * Items constructor.
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @param MagentoDefaultRenderer $subject
     * @param array $result
     * @return array
     */
    public function afterGetColumns(MagentoDefaultRenderer $subject, array $result): array
    {
        /** Check DR Setting Enabled or not. */
        if (!$this->config->getIsEnabled()) {
            return $result;
        }
        if ($subject->getItem()->getOrder()->getDrDutyFee() !== null ||
            $subject->getItem()->getOrder()->getDrIorTax() !== null) {
            unset($result['tax-percent']);
            unset($result['tax-amount']);
        }

        return $result;
    }
}
