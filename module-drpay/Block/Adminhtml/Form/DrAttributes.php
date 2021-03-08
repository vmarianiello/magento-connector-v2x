<?php
/**
 * Provides DR Attributes options export page
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Block\Adminhtml\Form;

use Digitalriver\DrPay\Model\ResourceModel\TaxTypes\CollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Get collection of DR custom attributes from custom table.
 * Class DrAttributes
 */
class DrAttributes extends Template
{
    /**
     * @var CollectionFactory
     */
    private $taxTypes;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param CollectionFactory $taxTypes
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $taxTypes,
        array $data = []
    ) {
        $this->taxTypes = $taxTypes;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve json
     *
     * @return string
     */
    public function getTaxValues(): string
    {
        $dataCollection = $this->taxTypes->create()->getData();
        return json_encode($dataCollection);
    }
}
