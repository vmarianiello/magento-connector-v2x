<?php
/**
 * Config Helper
 */

namespace Digitalriver\DrPay\Helper;

use Digitalriver\DrPay\Logger\Logger;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Pricing\PriceCurrencyInterface as PriceCurrencyInterface;

/**
 * Configuration Helper
 */
class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    const XML_PATH_STORE_INFO_NAME = 'general/store_information/name';

    const XML_PATH_STORE_INFO_PHONE = 'general/store_information/phone';

    const XML_PATH_STORE_INFO_HOURS = 'general/store_information/hours';

    const XML_PATH_STORE_INFO_STREET_LINE1 = 'general/store_information/street_line1';

    const XML_PATH_STORE_INFO_STREET_LINE2 = 'general/store_information/street_line2';

    const XML_PATH_STORE_INFO_CITY = 'general/store_information/city';

    const XML_PATH_STORE_INFO_POSTCODE = 'general/store_information/postcode';

    const XML_PATH_STORE_INFO_REGION_CODE = 'general/store_information/region_id';

    const XML_PATH_STORE_INFO_COUNTRY_CODE = 'general/store_information/country_id';

    const XML_PATH_STORE_INFO_VAT_NUMBER = 'general/store_information/merchant_vat_number';

    const XML_PATH_COUNTRY_CODE_PATH = 'general/country/default';

    const XML_PATH_ENABLE_DRPAY = 'dr_settings/config/active';

    const XML_PATH_DRAPI_BASE_URL = 'https://api.digitalriver.com';

    const XML_PATH_DROPIN_JS_URL = 'https://js.digitalriverws.com/v1/DigitalRiver.js';

    const XML_PATH_DROPIN_CSS_URL = 'https://js.digitalriverws.com/v1/css/DigitalRiver.css';

    const XML_PATH_DRAPI_PUBLIC_KEY = 'dr_settings/config/drapi_public_key';

    const XML_PATH_DRAPI_SECRET_KEY = 'dr_settings/config/drapi_secret_key';

    const XML_PATH_LOCALE = 'general/locale/code';

    const XML_PATH_PRICE_INCLUDES_TAX = 'tax/calculation/price_includes_tax';

    const XML_PATH_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    const XML_PATH_CATALOG_SYNC_ENABLE = 'dr_settings/catalog_sync/active';

    const XML_PATH_CATALOG_SYNC_BUNCH_SIZE = 'dr_settings/catalog_sync/batch_limit';

    const XML_PATH_CATALOG_SYNC_DEBUG_MODE = 'dr_settings/catalog_sync/debug_mode';

    const DEFAULT_SELLING_ENTITY = 'DR_INC-ENTITY';

    const DEFAULT_BATCH_SIZE = 250;

    /**
     * @param Context                                    $context
     * @param \Magento\Framework\HTTP\Client\Curl        $curl
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Store\Model\Information           $storeInfo
     * @param Logger                                     $logger
     */
    public function __construct(
        Context $context,
        \Digitalriver\DrPay\Framework\HTTP\Client\Curl $curl,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\Information $storeInfo,
        \Magento\Checkout\Model\Session $session,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        Logger $logger,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\Country $country,
        \Magento\Framework\Encryption\EncryptorInterface $enc,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        $this->storeInfo = $storeInfo;
        $this->session = $session;
        $this->currencyFactory = $currencyFactory;
        $this->priceCurrency = $priceCurrency;
        $this->regionFactory = $regionFactory;
        $this->country = $country;
        $this->_enc = $enc;
        parent::__construct($context);
        $this->_logger = $logger;
    }

    public function convertToBaseCurrency($price)
    {
        $currentCurrency = $this->getCurrentCurrencyCode();
        $baseCurrency = $this->getBaseCurrencyCode();
        $rate = $this->currencyFactory->create()->load($currentCurrency)->getAnyRate($baseCurrency);
        $returnValue = $this->round($price * $rate);
        return $returnValue;
    }

    public function clearSessionData()
    {
        $this->session->unsDrAccessToken();
        $this->session->unsSessionCheckSum();
        $this->session->unsDrResult();
        $this->session->unsGuestCustomerEmail();
        $this->session->unsDrCustomerId();
        $this->session->unsDrCheckoutId();
        $this->session->unsDrLockedInCheckoutId();
        $this->session->unsDrSourceId();
        $this->session->unsDrPaymentSessionId();
        $this->session->unsDrSellingEntity();
        $this->session->unsDrReadyForStorage();

        $this->session->unsDrTax();

        $this->session->unsDrProductTax();
        $this->session->unsDrProductTotal();
        $this->session->unsDrProductTotalExcl();

        $this->session->unsDrShippingTax();
        $this->session->unsDrShippingAndHandling();
        $this->session->unsDrShippingAndHandlingExcl();

        $this->session->unsDrOrderTotal();
        $this->session->unsIsDrIorSet();
    }

    public function isTaxInclusive($storecode = null)
    {
        return $this->getConfig(self::XML_PATH_PRICE_INCLUDES_TAX, $storecode);
    }

    public function getDefaultSellingEntity()
    {
        return self::DEFAULT_SELLING_ENTITY;
    }

    public function getPurchaseLocation($address)
    {
        $result = [];
        $countryId = $address->getCountryId();
        if (empty($countryId)) {
            $countryId = $this->getDefaultCountry();
        }
        $result['country'] = $countryId;

        $regionName = $address->getRegion();
        $state = $this->getRegionCodeByNameAndCountryId($regionName, $countryId);
        if ($state) {
            $result['state'] = $state;
        }
        if (!empty($postalCode = $address->getPostCode())) {
            $result['postalCode'] = $postalCode;
        }
        return $result;
    }

    public function getDrStoreInfo()
    {
        $address['address']['line1'] = $this->getConfig(self::XML_PATH_STORE_INFO_STREET_LINE1);
        $address['address']['line2'] = '';
        $address['address']['city'] = $this->getConfig(self::XML_PATH_STORE_INFO_CITY);
        $address['address']['country'] = $this->getConfig(self::XML_PATH_STORE_INFO_COUNTRY_CODE);
        $regionStoreCode = $this->getConfig(self::XML_PATH_STORE_INFO_REGION_CODE);
        if (!empty($regionStoreCode)) {
               $regionStoreCode = $this->getRegionCodeById($regionStoreCode);
               $address['address']['state'] = $regionStoreCode;
        }
        $address['address']['postalCode'] = $this->getConfig(self::XML_PATH_STORE_INFO_POSTCODE);

        return $address;
    }

    public function getRegionCodeByNameAndCountryId($regionName, $countryId)
    {
        $region = $this->loadRegion(null, null, $regionName, $countryId);
        if ($region) {
            return $region->getCode();
        }
        return '';
    }

    public function getRegionCodeById($regionId)
    {
        $result = null;
        $region = $this->loadRegion($regionId);
        if ($region->getId()) {
            $result = $region->getCode();
        }
        return $result;
    }

    private function canRegionBeLoaded($regionId = null, $regionCode = null, $regionName = null, $countryId = null)
    {
        return $regionId || $countryId && ($regionCode || $regionName);
    }

    public function loadRegion($regionId = null, $regionCode = null, $regionName = null, $countryId = null)
    {
        if (!$this->canRegionBeLoaded($regionId, $regionCode, $regionName, $countryId)) {
            return null;
        }
        $region = $this->regionFactory->create();
        // Load the region by the data provided
        if ($regionId) {
            $region->load($regionId);
        } elseif ($regionCode) {
            $region->loadByCode($regionCode, $countryId);
        } elseif ($regionName) {
            $region->loadByName($regionName, $countryId);
        }
        return $region;
    }

    public function getCountryId($countryName)
    {
        $countryId = '';
        $countryCollection = $this->country->getCollection();
        foreach ($countryCollection as $country) {
            if ($countryName == $country->getName()) {
                $countryId = $country->getCountryId();
                break;
            }
        }
        ($countryId) || $countryId = $countryName;
        $countryCollection = null;
        return $countryId;
    }

    public function getConfig($config_path, $storecode = null)
    {
        return $this->scopeConfig->getValue($config_path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getDefaultCountry($storecode = null)
    {
        return $this->getConfig(self::XML_PATH_COUNTRY_CODE_PATH, $storecode);
    }

    /**
     * @return mixed|null
     */
    public function getLocale($storecode = null)
    {
        return $this->getConfig(self::XML_PATH_LOCALE, $storecode);
    }

    /**
     * @return mixed|null
     */
    public function getIsEnabled($storecode = null)
    {
        return $this->getConfig(self::XML_PATH_ENABLE_DRPAY, $storecode);
    }

    /**
     * @return mixed|null
     */
    public function getSecretKey($storecode = null)
    {
        $secretKey = $this->getConfig(self::XML_PATH_DRAPI_SECRET_KEY, $storecode);
        return $this->_enc->decrypt($secretKey);
    }

    /**
     * @return mixed|null
     */
    public function getUrl($storecode = null)
    {
        return self::XML_PATH_DRAPI_BASE_URL;
    }

    /**
     * @return mixed|null
     */
    public function getDropInJsUrl($storecode = null)
    {
        return self::XML_PATH_DROPIN_JS_URL;
    }

    /**
     * @return mixed|null
     */
    public function getDropInCssUrl()
    {
        return self::XML_PATH_DROPIN_CSS_URL;
    }

    /**
     * Get Batch Size limit to fetch sync collection.
     *
     * @param  null $storeCode
     * @return int|null
     */
    public function getBatchSizeLimit($storeCode = null): ?int
    {
        $batchSize = $this->getConfig(self::XML_PATH_CATALOG_SYNC_BUNCH_SIZE, $storeCode);
        return isset($batchSize) ? (int)$batchSize : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Get Debug mode setting bool value
     *
     * @param  null $storeCode
     * @return bool
     */
    public function isDebugModeEnable($storeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_CATALOG_SYNC_DEBUG_MODE, $storeCode);
    }

    /**
     * Get Batch Size limit to fetch sync collection.
     *
     * @param  null $storeCode
     * @return bool
     */
    public function isCatalogSyncEnable($storeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_CATALOG_SYNC_ENABLE, $storeCode);
    }

    public function getCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    public function getCurrentCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    public function getBaseCurrencyCode()
    {
        return $this->storeManager->getStore()->getBaseCurrency()->getCode();
    }

    /**
     * @return mixed|null
     */
    public function getPublicKey($storecode = null)
    {
        $publicKey = $this->getConfig(self::XML_PATH_DRAPI_PUBLIC_KEY, $storecode);
        return $this->_enc->decrypt($publicKey);
    }

    public function round($amount)
    {
        return $this->priceCurrency->round($amount);
    }

    private function doCurl($method, $url, $data = null)
    {
        $secret = $this->getSecretKey();
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->addHeader("Authorization", "Bearer " . $secret);
        $this->_logger->info("\n\nDRAPI URL " . $url);

        switch ($method) {
            case 'POST':
                $this->_logger->info("\n\nDRAPI PAYLOAD " . json_encode($data));
                $this->curl->post($url, json_encode($data));
                break;
            case 'PUT':
                $this->_logger->info("\n\nDRAPI PAYLOAD " . json_encode($data));
                $this->curl->put($url, json_encode($data));
                break;
            default:
                $this->curl->get($url);
        }

        $result = $this->curl->getBody();
        $result = json_decode($result, true);
        $statusCode = $this->curl->getStatus();
        $success = true;
        $code = '';
        $parameter = '';
        if (isset($result['errors']) || !in_array($statusCode, ['200', '201', '204'])) {
            $code = isset($result['errors'][0]['code']) ? $result['errors'][0]['code'] : '';
            $result = isset($result['errors'][0]['message']) ? $result['errors'][0]['message'] : '';
            $parameter = isset($result['errors'][0]['parameter']) ? $result['errors'][0]['parameter'] : '';
            $success = false;
        }
        $result = ['success' => $success,
            'statusCode' => $statusCode,
            'code' => $code,
            'parameter' => $parameter,
            'message' => $result];
        $this->_logger->info("\n\nDRAPI RESPONSE: " . json_encode($result));
        return $result;
    }

    public function doCurlPut($service, $data)
    {
        $url = $this->getUrl() . '/' . $service;
        return $this->doCurl('PUT', $url, $data);
    }

    public function doCurlPost($service, $data)
    {
        $url = $this->getUrl() . '/' . $service;
        return $this->doCurl('POST', $url, $data);
    }

    public function doCurlList($service, $search = null)
    {
        $url = $this->getUrl() . '/' . $service;
        if (!empty($search) && is_array($search)) {
            $url .= '?' . http_build_query($search);
        }
        return $this->doCurl('GET', $url);
    }

    public function doCurlGet($service, $id)
    {
        $url = $this->getUrl() . '/' . $service . '/' . $id;
        return $this->doCurl('GET', $url);
    }

    public function doCurlDelete($url)
    {
        $this->_logger->info("DRAPI URL " . $url);
        $secret = $this->getSecretKey();
        $request = new \Zend\Http\Request();
        $httpHeaders = new \Zend\Http\Headers();
        $client = new \Zend\Http\Client();
        $httpHeaders->addHeaders(
            [
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type' => 'application/json'
            ]
        );
        $request->setHeaders($httpHeaders);
        $request->setMethod(\Zend\Http\Request::METHOD_DELETE);
        $request->setUri($url);
        $response = $client->send($request);
    }

    /**
     * Get Digital River logger Object
     *
     * @return Logger
     */
    public function getDrLogger(): Logger
    {
        return $this->_logger;
    }
}
