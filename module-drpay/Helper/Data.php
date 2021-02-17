<?php
/**
 * Digitalriver Helper
 */

namespace Digitalriver\DrPay\Helper;

use Magento\Framework\App\Helper\Context;

/**
 * Data manager class
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var session
     */
    protected $session;
    /**
     * @var Session
     */
    private $_customerSession;
    protected $drFactory;
    protected $jsonHelper;

    const CUSTOMER_TYPE_INDIVIDUAL = 'individual';
    const CUSTOMER_SEGMENT = 'CustomerSegment';
    const ERROR_CODE_ALREADY_EXISTS = 'already_exists';

    /**
     * @param Context                                              $context,
     * @param \Magento\Checkout\Model\Session                      $session,
     * @param \Magento\Quote\Api\CartManagementInterface           $_cartManagement,
     * @param \Magento\Customer\Model\Session                      $_customerSession,
     * @param \Magento\Checkout\Helper\Data                        $checkoutHelper,
     * @param \Digitalriver\DrPay\Model\DrConnectorFactory         $drFactory,
     * @param \Magento\Framework\Json\Helper\Data                  $jsonHelper,
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
     * @param \Digitalriver\DrPay\Logger\Logger                    $logger,
     * @param \Digitalriver\DrPay\Helper\Drapi                     $drapi,
     * @param \Digitalriver\DrPay\Helper\Comapi                    $comapi,
     * @param \Digitalriver\DrPay\Helper\Config                    $config
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Customer\Model\Session $_customerSession,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Digitalriver\DrPay\Model\DrConnectorFactory $drFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Digitalriver\DrPay\Logger\Logger $logger,
        \Digitalriver\DrPay\Helper\Drapi $drapi,
        \Digitalriver\DrPay\Helper\Comapi $comapi,
        \Digitalriver\DrPay\Helper\Config $config
    ) {
        $this->session = $session;
        $this->_customerSession = $_customerSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->jsonHelper = $jsonHelper;
        $this->drFactory = $drFactory;
        $this->remoteAddress = $remoteAddress;
        parent::__construct($context);
        $this->_logger = $logger;
        $this->_drapi = $drapi;
        $this->_comapi = $comapi;
        $this->config = $config;
    }

    public function logger($data)
    {
        $this->_logger->critical($data);
    }

    public function setSku($skuId, $data)
    {
        /* Required fields:
            1. skuId
            2. countryOfOrigin
            3. eccn
            4. taxCode
            5. name
        */
        /*
        SKU Fulfillment Types
            DR fulfilled physical
                1. sku.fulfill is true
                2. sku.taxCode belong to physical goods
                 manufactuerId, partNumber are still required
                 GC Product.mfrPartNumber = Sku.partNumber
                 GC InventoryLineItem.partNumber = GF Product ID

            DR fulfilled digital
                1. sku.fulfill is true
                2. sku.taxCode belong to non-physical goods

            Client fulfilled physical
                1. sku.fulfill is false
                2. sku.taxCode belong to physical goods
                 Since 2009.1.0, allow Client to create a Client fulfilled physical sku without part number
                 GC Product.mfrPartNumber is null if Sku.partNumber is not presented.
                 GC InventoryLineItem.partNumber would be {SITE_ID}_{Sku.partNumber} or
                    {SITE_ID}_{Sku.id} depends on the presence of sku.partNumber

            Client fulfilled digital
                1. sku.fulfill is false
                2. sku.taxCode belong to non-physical goods
        */
        if (empty($data) || !is_array($data)) {
            return ['success' => false,
                'code' => 'invalid_parameter',
                'message' => 'Missing SKU Data',
                'statusCode' => 400];
        }
        if (empty($skuId)) {
            // check if $data contains the "id" param
            if (isset($data['id'])) {
                $skuId = $data['id'];
                unset($data['id']);
            } else {
                return ['success' => false,
                'code' => 'invalid_parameter',
                'message' => 'Missing SKU ID',
                'statusCode' => 400];
            }
        }

        if (!isset($data['name'])) {
            return ['success' => false,
            'code' => 'invalid_parameter',
            'message' => 'Missing SKU Name',
            'statusCode' => 400];
        }
        if (!isset($data['eccn'])) {
            return ['success' => false,
            'code' => 'invalid_parameter',
            'message' => 'Missing SKU ECCN',
            'statusCode' => 400];
        }
        if (!isset($data['taxCode'])) {
            return ['success' => false,
            'code' => 'invalid_parameter',
            'message' => 'Missing SKU tax code',
            'statusCode' => 400];
        }
        if (!isset($data['countryOfOrigin'])) {
            return ['success' => false,
            'code' => 'invalid_parameter',
            'message' => 'Missing SKU country of origin',
            'statusCode' => 400];
        }

        return $this->_drapi->setSku($skuId, $data);
    }

    public function getSku($skuId)
    {
        if (empty($skuId)) {
            return ['success' => false,
            'code' => 'invalid_parameter',
            'message' => 'Missing SKU ID',
            'statusCode' => 400];
        }
        return $this->_drapi->getSku($skuId);
    }

    public function getShipToAddress($shippingAddress)
    {
        $return = [];

        if (!empty($shippingAddress->getCity())) {
            $street = $shippingAddress->getStreet();
            $return['address']['line1'] = isset($street[0]) ? $street[0] : '';
            $return['address']['line2'] = isset($street[1]) ? $street[1] : '';
            $return['address']['city'] = $shippingAddress->getCity();
            $return['address']['country'] = $shippingAddress->getCountryId();
            $return['address']['state'] = $this->config->getRegionCodeByNameAndCountryId(
                $shippingAddress->getRegion(),
                $shippingAddress->getCountryId()
            );
            $return['address']['postalCode'] = $shippingAddress->getPostcode();

            // The shipping address element in the quote does not contain email address
            $return['phone'] = $shippingAddress->getTelephone();
            $return['name']= $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname();
            $return['email'] = $shippingAddress->getEmail();
            $return['organization'] = ($shippingAddress->getCompany()) ? $shippingAddress->getCompany() : '';
        }
        return $return;
    }

    public function setCustomer($email)
    {
        try {
            $drCustomerId = $this->session->getDrCustomerId();
            if ($this->_customerSession->isLoggedIn()) {
                $drCustomerCreateId = sha1(
                    $email . $this->_customerSession->getCustomer()->getId()
                );
                if (empty($drCustomerId) || $drCustomerId != $drCustomerCreateId) {
                    $this->_logger->info("FUNCTION " . __FUNCTION__);
                    $data['id'] = $drCustomerCreateId;
                    // When implementing US TEMs, this will conditionally be 'business'
                    $data['type'] = self::CUSTOMER_TYPE_INDIVIDUAL;
                    $data['email'] = $email;
                    $data['locale'] = $this->config->getLocale();
                    $data['metadata']['CustomerSegment'] = self::CUSTOMER_SEGMENT;
                    $result = $this->_drapi->setCustomer($data);
                    if (($result['success'] == false
                        && $result['code'] == self::ERROR_CODE_ALREADY_EXISTS)
                        || $result['success']) {
                        $this->session->setDrCustomerId($drCustomerCreateId);
                    }
                }
            }
            return $drCustomerId;
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    /**
     * @param  mixed  $sourceId
     * @param  string $name
     * @return mixed|null
     */
    public function setCustomerSource($sourceId)
    {
        try {
            $this->_logger->info("FUNCTION " . __FUNCTION__);
            $result = $this->_drapi->setCustomerSource($sourceId);
            return $result;
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    /**
     * @return array|null
     */
    public function setCheckout($quote)
    {
        // add check if the connector is enabled
        if (!$this->config->getIsEnabled()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        if ($quote->getIsVirtual()) {
            $shippingAddress = $quote->getBillingAddress();
        }
        // basically only fire when in the checkout
        if (!$shippingAddress || !$shippingAddress->getCity()) {
            return;
        }

        $this->_logger->info("FUNCTION " . __FUNCTION__);
        try {
            $tax_inclusive = $this->config->isTaxInclusive();
            $data = [];
            $data['email'] = $this->session->getGuestCustomerEmail();

            $data['metadata']['QuoteID'] = ($quote->getId()) ? $quote->getId() : 0;
            $data['taxInclusive'] = $tax_inclusive ? true : false;
            $data['currency'] = $this->config->getCurrencyCode();

            // get the shipFrom from the store info
            $data['shipFrom'] = $this->config->getDrStoreInfo();

            //apply the browser IP to the payload
            $ip = $this->remoteAddress->getRemoteAddress();
            $data['browserIp'] = $ip;

            // next get the shipping country from either the shipping address
            // or the default country for the site

            // attempt to create the customer
            $drCustomerId = $this->setCustomer($shippingAddress->getEmail());
            if ($drCustomerId) {
                $data['customerId'] = (string) $drCustomerId;
            }

            if (!$quote->getIsVirtual()) {
                $shipTo = $this->getShipToAddress($shippingAddress);
                if ($shipTo) {
                    $data['shipTo'] = $shipTo;
                }
            }

            $data['purchaseLocation'] = $this->config->getPurchaseLocation($shippingAddress);

            $data['locale'] = $this->config->getLocale();

            $productTotal = 0;
            $subTotalDiscount = 0;
            $quoteItems = $quote->getAllItems();
            if (empty($quoteItems)) {
                return false;
            }
            foreach ($quoteItems as $item) {
                if ($item->getProductType() ==
                    \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE
                    || $item->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE) {
                    continue;
                }
                if ($item->getParentItemId()) {
                    if ($item->getParentItem()->getProductType() ==
                        \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                        $item = $item->getParentItem();
                    }
                }
                $lineItem =  [];
                $lineItem['quantity'] = $item->getQty();
                if ($item->getParentItemId()) {
                    if ($item->getParentItem()->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE) {
                        $lineItem['quantity'] = $item->getQty() * $item->getParentItem()->getQty();
                    }
                }
                $sku = $item->getSku();
                $price = $item->getRowTotal();

                if ($tax_inclusive) {
                    $price = $item->getRowTotalInclTax();
                    $lineItem['metadata']['productPriceSubTotalInclTax'] = round($price, 2);
                } else {
                    $lineItem['metadata']['productPriceSubTotal'] = round($price, 2);
                }

                $lineItem['metadata']['magento_quote_item_id'] = $item->getId();

                $productTotal += $price;

                if ($item->getDiscountAmount() > 0) {
                    $price = $price - $item->getDiscountAmount();
                }
                $subTotalDiscount += $item->getDiscountAmount();
                $lineItem['metadata']['productDiscount'] = round($item->getDiscountAmount(), 2);

                if ($price <= 0) {
                    $price = 0;
                }
                $lineItem['skuId'] = $sku;
                $lineItem['aggregatePrice'] = round($price, 2);

                if ($item->getParentItemId()) {
                    $lineItem['metadata']['parentExternalReferenceId'] = $item->getParentItem()->getSku();
                }

                $data['items'][] = $lineItem;
            }

            $data['metadata']['subTotalDiscount'] = $subTotalDiscount;
            $data['metadata']['shippingDiscount'] = 0;

            $shippingAmount = 0;

            if (!$quote->getIsVirtual()) {
                $shippingAmount = $quote->getShippingAddress()->getShippingAmountForDiscount();
                if (empty($shippingAmount)) {
                    $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
                }
                $data['metadata']['shippingAmount'] = $shippingAmount;
                $data['metadata']['shippingDiscount'] = $quote->getShippingAddress()->getShippingDiscountAmount();
                if ($shippingAmount > 0 && $quote->getShippingAddress()->getShippingDiscountAmount() > 0) {
                    $shippingAmount = $shippingAmount - $quote->getShippingAddress()->getShippingDiscountAmount();
                }
                $shippingMethod = $quote->getShippingAddress()->getShippingMethod();

                $shippingChoice['amount'] = $shippingAmount;
                $shippingChoice['description'] = $quote->getShippingAddress()->getShippingDescription();
                $shippingChoice['serviceLevel'] = 'SG';
                $data['shippingChoice'] = $shippingChoice;
            }

            /*******************************************************/
            /*    PROCESS THE PAYLOAD THRU DR API FLEET */
            /*******************************************************/
            $result = $this->_drapi->setCheckout($data);
            if ($result['success'] == false) {
                $this->session->setDrQuoteError(true);
                $this->session->unsSessionCheckSum();

                $quote->setBaseDrDutyFee(0);
                $quote->setBaseDrIorTax(0);
                $this->session->setDrIorTax(0);
                $this->session->setDrDutyFee(0);
                $this->session->setIsDrIorSet(false);
                $quote->setIsDrIorSet(false);
                return false;
            }

            $this->session->setDrQuoteError(false);
            $this->session->setDrCheckoutId($result['id']);

            $this->_logger->info("CHECKOUT " . json_encode($result));

            $shippingTax = $result['shippingTax'];
            $productTax = $result['productTax'];
            $productTotalExclTax = $result['productTotalExclTax'];
            $shippingTotalExclTax = $result['shippingTotalExclTax'];

            $quote->setShippingInclTax($shippingAmount);

            $this->session->setDrShippingTax($shippingTax);
            $this->session->setDrShippingAndHandling($shippingAmount);
            $this->session->setDrShippingAndHandlingExcl($shippingTotalExclTax);

            $orderTotal = $result['orderTotal'];
            $quote->setGrandTotal($orderTotal);
            $quote->setBaseGrandTotal($this->config->convertToBaseCurrency($orderTotal));
            $this->session->setDrOrderTotal($orderTotal);

            $this->session->setDrProductTax($productTax);
            $this->session->setDrProductTotal($productTotal);
            $this->session->setDrProductTotalExcl($productTotalExclTax);

            if (isset($result['importerOfRecordTax']) && $result['importerOfRecordTax']) {
                $drtax = 0;
                $quote->setTaxAmount($drtax);
                $quote->setBaseTaxAmount($drtax);
                $quote->setDrTax($drtax);
                $quote->setBaseDrDutyFee($result['totalDuty']);
                $quote->setBaseDrIorTax($result['orderTax']);
                $quote->setIsDrIorSet(true);

                $this->session->setDrIorTax($result['orderTax']);
                $this->session->setDrDutyFee($result['totalDuty']);
                $this->session->setIsDrIorSet(true);
            } else {
                $drtax = $result['orderTax'];
                $quote->setTaxAmount($drtax);
                $quote->setBaseTaxAmount($drtax);
                $quote->setDrTax($drtax);

                $quote->setBaseDrDutyFee(0);
                $quote->setBaseDrIorTax(0);
                $quote->setIsDrIorSet(false);

                $this->session->setDrIorTax(0);
                $this->session->setDrDutyFee(0);
                $this->session->setIsDrIorSet(false);
            }
            $this->session->setDrPaymentSessionId($result['paymentSessionId']);
            $this->session->setDrSellingEntity($result['sellingEntity']);

            $this->session->setDrTax($drtax);
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    public function getCustomer($customerId)
    {
        $result = $this->_drapi->getCustomer($customerId);
        return $result;
    }

    /**
     * @return array|null
     */
    public function getSavedSources($customerId)
    {
        try {
            $this->_logger->info("FUNCTION " . __FUNCTION__);
            $cardData = [];
            $result = $this->getCustomer($customerId);
            if ($result['success']) {
                $customer = $result['message'];
                if (isset($customer['sources'])) {
                    foreach ($customer['sources'] as $source) {
                        if (isset($source['type']) && $source['reusable'] === true && $source['type'] == 'creditCard') {
                            $content = __("Credit Card: ending with ") .
                                $source['creditCard']['lastFourDigits'];
                            $struct = [];
                            $struct['content'] = $content;
                            $struct['sourceId'] = $source['id'];
                            $struct['sourceClientSecret'] = $source['clientSecret'];
                            $cardData[$source['id']] = $struct;
                        }
                    }
                    $result['message'] = $cardData;
                } else {
                    $result['success'] = false;
                }
            } else {
                $result['success'] = false;
            }
            return $result;
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    public function setCheckoutUpdate($checkoutId, $data)
    {
        try {
            $result = $this->_drapi->setCheckoutUpdate($checkoutId, $data);
            return $result;
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    /**
     * @param  mixed $accessToken
     * @return mixed|null
     */
    public function setOrder($checkoutId)
    {
        try {
            $this->_logger->info("FUNCTION " . __FUNCTION__);
            $result = $this->_drapi->setOrder($checkoutId);
            return $result;
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    public function getSourceDetails($sourceId)
    {
        try {
            $this->_logger->info("FUNCTION " . __FUNCTION__);
            $result = $this->_drapi->getSourceDetails($sourceId);
            if (!$result['success']) {
                throw new \Magento\Framework\Exception\LocalizedException(__($result['message']));
            }
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * This is a COM API specific call
     *
     * @object $order
     * @return type
     */
    public function setOrderStateComplete($order)
    {
        try {
            if ($order->getDrOrderId()) {
                $this->_logger->info("FUNCTION " . __FUNCTION__);
                $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                if (!$drModel->getId()) {
                    return false;
                }
                if ($drModel->getPostStatus() == 1) {
                    return false;
                }
                $drApiType = $order->getDrApiType();
                if ($drApiType != 'drapi') {
                    $result = $this->_comapi->setOrderStateComplete($order);
                    if ($result['success']) {
                        $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                        $drModel->setPostStatus(1);
                        $drModel->save();
                    }
                    return $result['success'];
                }
                return false;
            }
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
        return false;
    }

    /**
     *
     * @return type
     */
    public function setOrderCancellation($order)
    {
        try {
            $this->_logger->info("FUNCTION " . __FUNCTION__);
            $this->_logger->info(json_encode($order));

            $result = $this->_drapi->setOrderCancellation($order);
            $this->session->unsDrCheckoutId();
            $this->session->unsDrLockedInCheckoutId();
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
    }

    /**
     *
     * @return type
     */
    public function setRefundRequest($creditmemo)
    {
        $result = true;
        try {
            $order = $creditmemo->getOrder();
            if ($order->getDrOrderId()) {
                $drApiType = $order->getDrApiType();
                if ($drApiType == 'drapi') {
                    $result = $this->_drapi->setRefundRequest($creditmemo);
                } else {
                    $result = $this->_comapi->setRefundRequest($creditmemo);
                }
            }
        } catch (Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Function to validate Quote for any errors, As in some cases Magento encounters an exception.
     * To avoid this, Quote is validated before proceeding for order processing
     *
     * @param  object $quote
     * @return bool $isValidQuote
     **/
    public function isQuoteValid(\Magento\Quote\Model\Quote $quote)
    {
        $this->_logger->info("FUNCTION " . __FUNCTION__);
        $isValidQuote = false;
        try {
            $errors         = $quote->getErrors();
            $isValidQuote   = (empty($errors)) ? true : false;
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $le->getMessage());
        } catch (\Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
        return $isValidQuote;
    }

    /**
     * Function to fetch Billing & Shipping address from DR order creation response
     *
     * @param array $drResponse
     *
     * @return array $returnAddress
     */
    public function getBillingAddressFromSource($source)
    {
        $this->_logger->info("FUNCTION " . __FUNCTION__);
        $this->_logger->info("SOURCE DATA " . json_encode($source));
        $returnAddress = false;
        if ($source['success']) {
            $sourceInfo = $source['message']['owner'];
            $sourceInfo['address']['country'] = $this->config->getCountryId($sourceInfo['address']['country']);
            $returnAddress = [
                'firstname'     => $sourceInfo['firstName'],
                'lastname'      => $sourceInfo['lastName'],
                'street'        => $sourceInfo['address']['line1'],
                'city'          => $sourceInfo['address']['city'],
                'postcode'      => $sourceInfo['address']['postalCode'],
                'country_id'    => $sourceInfo['address']['country']
            ];
            if (isset($sourceInfo['address']['state'])) {
                // test if the state is a code
                $region = $this->config->loadRegion(
                    null,
                    $sourceInfo['address']['state'],
                    null,
                    $sourceInfo['address']['country']
                );
                if (!$region->getCode()) {
                    // try as the state name
                    $region = $this->config->loadRegion(
                        null,
                        null,
                        $sourceInfo['address']['state'],
                        $sourceInfo['address']['country']
                    );
                }
                $returnAddress['region'] = $region->getCode();
                $returnAddress['region_id'] = $region->getRegionId();
            } else {
                $returnAddress['region'] = null;
                $returnAddress['region_id'] = null;
            }
        }
        $this->_logger->info("RETURN ADDRESS " . json_encode($returnAddress));
        return $returnAddress;
    }

    private function getFulfillmentRequest($order, $lineItems)
    {
        $fulfilled = $this->_drapi->getFulfillmentRequest($order, $lineItems);
        // for each lineitem, get magento's quantity shipped value.
        // if the product has already been fulfilled, then subtract the quantity from magento's qty shipped
        if ($fulfilled['success'] == true
            && isset($fulfilled['message']['data'])
            && !empty($fulfilled['message']['data'])) {
            foreach ($fulfilled['message']['data'] as $fulfillObject) {
                foreach ($fulfillObject['items'] as $fulfilledItems) {
                    // deduct the lineitem quantity from the qty already shipped
                    foreach ($lineItems as $itemId => &$item) {
                        if ($item['sku'] == $fulfilledItems['skuId']) {
                            $item['quantity'] -= $fulfilledItems['quantity'];
                        }
                    }
                }
            }
        }
        return $lineItems;
    }

    /**
     * Function to send EFN request to DR when Invoice/Shipment created from Magento Admin
     * Only Invoice/Shipment Success cases are sent
     *
     * @param array  $lineItems
     * @param object $order
     *
     * @return array $result
     */
    public function setFulfillmentRequest($lineItems, $order)
    {
        $this->_logger->info("FUNCTION " . __FUNCTION__);
        try {
            if ($order->getDrOrderId()) {
                $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                if (!$drModel->getId() || $drModel->getPostStatus() == 1) {
                    return;
                }

                $drApiType = $order->getDrApiType();
                if ($drApiType == 'drapi') {

                    // get the fulfillments already made to DR
                    $lineItems = $this->getFulfillmentRequest($order, $lineItems);
                    $result = $this->_drapi->setFulfillmentRequest($lineItems, $order);
                } else {
                    $result = $this->_comapi->setFulfillmentRequest($lineItems, $order);
                }

                if ($result['success']) {
                    // Post Status updated only if entire order items are fulfilled
                    $canInvoice = $order->canInvoice(); // returns true for pending items
                    $canShip = $order->canShip();  // returns true for pending items
                    // Return true if both invoice and shipment are false, i.e. No items to fulfill
                    if (empty($canInvoice) && empty($canShip)) {
                        // if all the quantites are satisfied then mark as 1
                        $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                        $drModel->setPostStatus(1);
                        $drModel->save();
                        $comment = 'Order fulfilled';
                    } else {
                        $comment = 'This order has been partially fulfilled. One or more items remain to be shipped';
                    }
                    $order->addStatusToHistory($order->getStatus(), __($comment));
                }
            } else {
                $this->_logger->error('Error: ' . __FUNCTION__ . ': Empty DR Order Id');
            }
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        } // end: try

        return $result;
    }

    /**
     * Function to send EFN request to DR when @OrderItem is cancelled from Magento Admin
     *
     * @param array  $lineItems
     * @param object $order
     *
     * @return array $result
     */
    public function setFulfillmentCancellation($lineItems, $order)
    {
        $this->_logger->info("FUNCTION " . __FUNCTION__);
        try {
            if ($order->getDrOrderId()) {
                $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');

                if (!$drModel->getId() || $drModel->getPostStatus() == 1) {
                    return;
                }

                $drApiType = $order->getDrApiType();
                if ($drApiType == 'drapi') {
                    $result = $this->_drapi->setFulfillmentCancellation($lineItems, $order);
                } else {
                    $result = $this->_comapi->setFulfillmentCancellation($lineItems, $order);
                }
                $this->_logger->info(json_encode($result));
                // Status Update: Existing code used according to review changes
                if ($result['success']) {
                    $comment = 'Push notification: Order cancellation';
                    $order->addStatusToHistory($order->getStatus(), __($comment));
                }
                return ($result['success']);
            }
        } catch (\Exception $e) {
            $this->_logger->error('Error: ' . __FUNCTION__ . ': ' . $e->getMessage());
        }
        return false;
    }
}
