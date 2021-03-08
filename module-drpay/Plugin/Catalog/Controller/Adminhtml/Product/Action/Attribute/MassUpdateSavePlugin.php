<?php
/**
 * Perform operation after mass update attributes successfully completed from product page
 *
 * @category   Digitalriver
 * @package    Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Plugin\Catalog\Controller\Adminhtml\Product\Action\Attribute;

use Digitalriver\DrPay\Helper\Config;
use Digitalriver\DrPay\Model\CatalogSyncRepository;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Action\Attribute\Save;
use Magento\Catalog\Helper\Product\Edit\Action\Attribute;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * Add Records to Catalog Sync Queue table after mass action update attributes from product
 */
class MassUpdateSavePlugin
{
    private const ATTRIBUTES = 'attributes';
    private const ENTITY_ID = 'entity_id';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CatalogSyncRepository
     */
    private $catalogSyncRepository;

    /**
     * @var Attribute
     */
    private $attributeHelper;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Config
     */
    private $drConfig;

    /**
     * MassUpdateSavePlugin constructor.
     * @param Attribute $attributeHelper
     * @param ProductRepositoryInterface $productRepository
     * @param CatalogSyncRepository $catalogSyncRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Config $drConfig
     */
    public function __construct(
        Attribute $attributeHelper,
        ProductRepositoryInterface $productRepository,
        CatalogSyncRepository $catalogSyncRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Config $drConfig
    ) {
        $this->attributeHelper = $attributeHelper;
        $this->productRepository = $productRepository;
        $this->catalogSyncRepository = $catalogSyncRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->drConfig = $drConfig;
    }

    /**
     * Add Records of each product to the dr_Catalog_sync_queue table.
     *
     * @param Save $subject
     *
     * @param ResultInterface $result
     * @return ResultInterface|void
     * @throws NoSuchEntityException
     */
    public function afterExecute(Save $subject, ResultInterface $result)
    {
        if (!$this->drConfig->getIsEnabled() || !$this->drConfig->isCatalogSyncEnable()) {
            return $result;
        }

        $request = $subject->getRequest();
        $productIds = $this->attributeHelper->getProductIds();

        $massUpdateAttributes = $request->getParam(self::ATTRIBUTES);
        if (isset($massUpdateAttributes)) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(self::ENTITY_ID, $productIds, 'in')
                ->create();
            $products = $this->productRepository->getList($searchCriteria);
            if ($products->getTotalCount()) {
                foreach ($products->getItems() as $product) {
                    /** Skip for the bundle and grouped product type */
                    $productTypeId = $product->getTypeId();
                    if ($productTypeId === BundleType::TYPE_CODE ||
                        $productTypeId === Grouped::TYPE_CODE) {
                        continue;
                    }

                    $productAttribute = $this->catalogSyncRepository->createMassRequestData(
                        $massUpdateAttributes,
                        $product
                    );

                    $this->catalogSyncRepository->saveCatalogSync((int)$product->getId(), $productAttribute);
                }
            }
        }
        return $result;
    }
}
