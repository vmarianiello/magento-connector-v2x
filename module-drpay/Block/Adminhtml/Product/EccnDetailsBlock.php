<?php
/**
 * Used for rendering Attribute details template in product edit page
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Block\Adminhtml\Product;

use Digitalriver\DrPay\Model\ResourceModel\EccnCode\CollectionFactory as EccnCodeCollectionFactory;
use Magento\Backend\Block\Template\Context;

/**
 * Class Eccn Attribute Details Block
 */
class EccnDetailsBlock extends \Magento\Backend\Block\Template
{
    /**
     * Block template.
     *
     * @var string
     */
    protected $_template = 'eccn_details_tab.phtml';

    /**
     * @var EccnCodeCollectionFactory
     */
    private $collectionFactory;

    /**
     * Eccn constructor.
     * @param Context $context
     * @param EccnCodeCollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        EccnCodeCollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * returns ECCN attribute details
     *
     * @return array
     */
    public function getEccnDetails(): array
    {
        $eccnCode = $this->collectionFactory->create();
        $eccnData = $eccnCode->getData();

        $options = [];
        foreach ($eccnData as $item) {
            $options []  =
                [
                    'classification_code' => $item['classification_code'],
                    'description' => $item['description'],
                    'notes' => $item['notes']
                ];
        }
        return $options;
    }
}
