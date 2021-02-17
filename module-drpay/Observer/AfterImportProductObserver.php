<?php
/**
 * Observer to save catalog sync data after product import successfully
 *
 * @category   Digitalriver
 * @package    Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Observer;

use Digitalriver\DrPay\Helper\Config;
use Digitalriver\DrPay\Logger\Logger as DrLogger;
use Digitalriver\DrPay\Model\CatalogSyncRepository;
use Digitalriver\DrPay\Model\ResourceModel\CatalogSync;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * Save Product Attribute to db_catalog_sync_queue table after import product done.
 */
class AfterImportProductObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $drConfig;

    /**
     * @var CatalogSync
     */
    private $catalogSyncResource;

    /**
     * @var DrLogger
     */
    private $drLogger;

    /**
     * @var CatalogSyncRepository;
     */
    private $catalogSyncRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * AfterImportProductObserver constructor.
     * @param Config $drConfig
     * @param DrLogger $drLogger
     * @param CatalogSyncRepository $catalogSyncRepository
     * @param ProductRepositoryInterface $productRepository
     * @param CatalogSync $catalogSyncResource
     */
    public function __construct(
        Config $drConfig,
        DrLogger $drLogger,
        CatalogSyncRepository $catalogSyncRepository,
        ProductRepositoryInterface $productRepository,
        CatalogSync $catalogSyncResource
    ) {
        $this->drConfig = $drConfig;
        $this->drLogger = $drLogger;
        $this->catalogSyncRepository = $catalogSyncRepository;
        $this->productRepository = $productRepository;
        $this->catalogSyncResource = $catalogSyncResource;
    }

    /**
     * Action after data import. Save updated attribute value to dr_catalog_sync_queue table.
     *
     * @param Observer $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        if (!$this->drConfig->getIsEnabled() || !$this->drConfig->isCatalogSyncEnable()) {
            return;
        }

        $adapter = $observer->getEvent()->getAdapter();
        if ($products = $observer->getEvent()->getBunch()) {
            foreach ($products as $product) {
                $productId = (int)$adapter->getNewSku($product[ImportProduct::COL_SKU])['entity_id'];
                try {
                    $productData = $this->productRepository->getById($productId);

                    /** Skip for the bundle and grouped product type */
                    $productTypeId = $productData->getTypeId();
                    if ($productTypeId === BundleType::TYPE_CODE ||
                        $productTypeId === Grouped::TYPE_CODE) {
                        continue;
                    }

                    $productAttribute = $this->catalogSyncRepository->createMassRequestData($product, $productData);
                    $this->catalogSyncRepository->saveCatalogSync($productId, $productAttribute);
                } catch (NoSuchEntityException $e) {
                    $this->drLogger->error(sprintf('Import Product Id %s Error', $productId), [$e->getMessage()]);
                }
            }
        }
    }
}
