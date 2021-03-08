<?php declare(strict_types=1);

/**
 * Hide Header Tax amount and Tax Percent column once tax fee or ior tax available
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Plugin\Block\Adminhtml\Order\View;

use Digitalriver\DrPay\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Block\Adminhtml\Order\View\Items as MagentoItems;

/**
 * Hide Header Tax amount and Tax Percent column
 */
class Items
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
     * @param MagentoItems $subject
     * @param array $result
     * @return array
     * @throws LocalizedException
     */
    public function afterGetColumns(MagentoItems $subject, array $result): array
    {
        /** Check DR Setting Enabled or not. */
        if (!$this->config->getIsEnabled()) {
            return $result;
        }
        if ($subject->getOrder()->getDrDutyFee() !== null ||
            $subject->getOrder()->getDrIorTax() !== null) {
            unset($result['tax-percent']);
            unset($result['tax-amount']);
        }

        return $result;
    }
}
