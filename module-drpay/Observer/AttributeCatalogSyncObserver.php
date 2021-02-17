<?php
/**
 * Save Record to catalog sync table after add/update product
 *
 * @category   Digitalriver
 * @package    Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Observer;

use Digitalriver\DrPay\Model\CatalogSyncRepository;
use Digitalriver\DrPay\Helper\Config;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * Save Product Attribute to db_catalog_sync_queue table after add/update product
 */
class AttributeCatalogSyncObserver implements ObserverInterface
{
    /**
     * @var CatalogSyncRepository
     */
    private $catalogSyncRepository;

    /**
     * @var Config
     */
    private $drConfig;

    /**
     * AttributeCatalogSyncObserver constructor.
     * @param Config $drConfig
     * @param CatalogSyncRepository $catalogSyncRepository
     */
    public function __construct(
        Config $drConfig,
        CatalogSyncRepository $catalogSyncRepository
    ) {
        $this->drConfig = $drConfig;
        $this->catalogSyncRepository = $catalogSyncRepository;
    }

    /**
     * Process source items during product saving via controller.
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer): void
    {
        if (!$this->drConfig->getIsEnabled() || !$this->drConfig->isCatalogSyncEnable()) {
            return;
        }

        /** @var ProductInterface $product */
        $product = $observer->getEvent()->getProduct();

        /** Skip for the bundle and grouped product type */
        $productTypeId = $product->getTypeId();
        if ($productTypeId === BundleType::TYPE_CODE || $productTypeId === Grouped::TYPE_CODE) {
            return;
        }
        $productAttribute = $this->catalogSyncRepository->createRequestData($product);
        $productId = (int)$product->getId();

        $this->catalogSyncRepository->saveCatalogSync($productId, $productAttribute);
    }
}
