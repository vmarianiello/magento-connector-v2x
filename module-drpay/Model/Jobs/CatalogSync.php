<?php
/**
 * Catalog Sync Cron Execute
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Model\Jobs;

use Digitalriver\DrPay\Api\CatalogSyncRepositoryInterface;
use Digitalriver\DrPay\Api\Data\CatalogSyncInterface;
use Digitalriver\DrPay\Api\Data\CatalogSyncInterfaceFactory;
use Digitalriver\DrPay\Helper\Config;
use Digitalriver\DrPay\Helper\Data as DrHelper;
use Digitalriver\DrPay\Logger\Logger;
use Digitalriver\DrPay\Model\CatalogSyncRepository as SyncRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime;

/**
 * CatalogSync Cron Model
 */
class CatalogSync
{
    private const RESPONSE_CODE_400 = 400;
    private const RESPONSE_CODE_500 = 500;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var EmailNotification
     */
    private $emailNotification;

    /**
     * @var Config
     */
    private $drConfig;

    /**
     * @var DrHelper
     */
    private $drHelper;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var CatalogSyncRepositoryInterface
     */
    private $catalogSyncRepository;

    /**
     * @var CatalogSyncInterfaceFactory
     */
    private $catalogSyncFactory;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var SerializerInterface
     */
    private $serialize;

    /**
     * Core session model
     *
     * @var Generic
     */
    protected $cronSession;

    /**
     * CatalogSync constructor.
     * @param EmailNotification $emailNotification
     * @param Config $drConfig
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CatalogSyncRepositoryInterface $catalogSyncRepository
     * @param CatalogSyncInterfaceFactory $catalogSyncFactory
     * @param SortOrderBuilder $sortOrderBuilder
     * @param SerializerInterface $serialize
     * @param Generic $cronSession
     * @param DrHelper $drHelper
     */
    public function __construct(
        EmailNotification $emailNotification,
        Config $drConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CatalogSyncRepositoryInterface $catalogSyncRepository,
        CatalogSyncInterfaceFactory $catalogSyncFactory,
        SortOrderBuilder $sortOrderBuilder,
        SerializerInterface $serialize,
        Generic $cronSession,
        DrHelper $drHelper
    ) {
        $this->emailNotification = $emailNotification;
        $this->drConfig = $drConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->catalogSyncRepository = $catalogSyncRepository;
        $this->catalogSyncFactory = $catalogSyncFactory;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->serialize = $serialize;
        $this->cronSession = $cronSession;
        $this->drHelper = $drHelper;
        $this->logger = $this->drConfig->getDrLogger();
    }

    /**
     * Run Catalog Sync Cron Jobs
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(): void
    {
        $this->runCron();
    }

    /**
     * Get List by search criteria
     *
     * @return SearchResultsInterface|null
     */
    public function getCatalogSyncList(): ?SearchResultsInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SyncRepository::STATUS, SyncRepository::STATUS_PENDING)
            ->create();

        $batchSizeLimit = $this->getBunchSizeLimit();
        $searchCriteria->setPageSize($batchSizeLimit);

        /** @var SortOrder $sortOrderBuilder */
        $sortOrder = $this->sortOrderBuilder->setField('entity_id')
            ->setDirection(SortOrder::SORT_ASC)
            ->create();
        $searchCriteria->setSortOrders([$sortOrder]);

        return $this->getList($searchCriteria);
    }

    /**
     * Run Cron Job to sync catalog sku with DR API Call
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function runCron(): void
    {
        if (!$this->drConfig->getIsEnabled() || !$this->drConfig->isCatalogSyncEnable()) {
            return;
        }
        $syncItems = $this->getCatalogSyncList();
        if (!$syncItems->getTotalCount()) {
            return;
        }

        $isDebugEnable = $this->drConfig->isDebugModeEnable();
        $data = $failedItemIds = [];
        foreach ($syncItems->getItems() as $item) {
            $requestData = $this->serialize->unserialize($item->getRequestData());

            $catalogSync = $this->catalogSyncFactory->create();
            $productId = (int)$item->getProductId();
            $sku = $item->getProductSku();
            $catalogSync->setId((int)$item->getId());
            $catalogSync->setProductId($productId);
            $catalogSync->setSyncedToDrAt((new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT));
            if (is_array($requestData)) {
                $data[SyncRepository::ECCN] = $requestData[SyncRepository::ECCN];
                $data[SyncRepository::TAX_CODE] = $requestData[SyncRepository::TAX_CODE];
                $data[SyncRepository::COUNTRY_OF_ORIGIN] = $requestData[SyncRepository::COUNTRY_OF_ORIGIN];
                $data[SyncRepository::NAME] = $requestData[SyncRepository::NAME];
                $data[SyncRepository::FULFILL] = $requestData[SyncRepository::FULFILL];
                $data[SyncRepository::PART_NUMBER] = $requestData[SyncRepository::PART_NUMBER];
                $data[SyncRepository::HS_CODE] = $requestData[SyncRepository::HS_CODE];
            }
            /** Call The Digital River Catalog SKU API */
            if ($isDebugEnable) {
                $this->logger->info('Catalog SKU ID Value ' . $sku);
                $this->logger->info('Catalog SKU API Call Payload ' . json_encode($data));
            }
            $response = $this->drHelper->setSku($sku, $data);
            if ($isDebugEnable) {
                $this->logger->info('Response Catalog SKU API ' . json_encode($response));
            }

            $statusCode = $response['statusCode'];
            $responseData = $this->serialize->serialize($response);
            $catalogSync->setResponseData($responseData);

            if (isset($response['success']) && !empty($response['success'])) {
                $catalogSync->setStatus(SyncRepository::STATUS_SUCCESS);
            } else {
                $status = SyncRepository::STATUS_PENDING;
                if ($statusCode >= self::RESPONSE_CODE_400 &&
                    $statusCode < self::RESPONSE_CODE_500) {
                    $status = SyncRepository::STATUS_FAIL;
                    $failedItemIds[] = $productId;
                }
                $catalogSync->setStatus($status);
            }
            /** Save Catalog Sync Entry */
            $this->saveCatalogSyncResponse($catalogSync);
        }

        /** Check Failed Items Status Exists, If Yes, send errors mail with ids. */
        if (count($failedItemIds) > 0) {
            $productStatusFailed = array_unique($failedItemIds);
            if ($isDebugEnable) {
                $this->logger->error('Failed Response for Product Id, ', [$productStatusFailed]);
            }
            $this->emailNotification->sendErrorsMail($productStatusFailed);
        }
    }

    /**
     * Save Catalog Sync DR Response
     *
     * @param CatalogSyncInterface $catalogSync
     * @return void
     */
    private function saveCatalogSyncResponse(CatalogSyncInterface $catalogSync): void
    {
        try {
            $this->catalogSyncRepository->save($catalogSync);
        } catch (LocalizedException $exception) {
            $this->logger->error('Cant save sync record.', [$exception]);
        }
    }

    /**
     * Save Catalog Sync DR Response
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface|null
     */
    private function getList(SearchCriteriaInterface $searchCriteria): ?SearchResultsInterface
    {
        $syncItems = null;
        try {
            $syncItems = $this->catalogSyncRepository->getList($searchCriteria);
        } catch (LocalizedException $exception) {
            $this->logger->error('Catalog Sync get list fetch error', [$exception]);
        }

        return $syncItems;
    }

    /**
     * Get Bunch size limit
     * @return int
     */
    public function getBunchSizeLimit(): int
    {
        return (int)$this->drConfig->getBatchSizeLimit();
    }
}
