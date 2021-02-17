<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Model\DropIn;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProvider implements ConfigProviderInterface
{
    const PAYMENT_METHOD_DROPIN_CODE = 'drpay_dropin';
    /**
     * @var string[]
     */
    protected $_methodCode = self::PAYMENT_METHOD_DROPIN_CODE;
    /**
     * $_method.
     *
     * @var Magento\Payment\Helper\Data
     */
    protected $_method;
    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * __construct constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param Session       $checkoutSession
     * @param Escaper       $escaper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        CheckoutSession $checkoutSession,
        Escaper $escaper,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $enc,
        \Digitalriver\DrPay\Helper\Config $config
    ) {
        $this->_method = $paymentHelper->getMethodInstance($this->_methodCode);
        $this->escaper = $escaper;
        $this->checkoutSession = $checkoutSession;
        $this->_scopeConfig = $scopeConfig;
        $this->_enc = $enc;
        $this->config = $config;
    }

    /**
     * getConfig function to return cofig data to payment renderer.
     *
     * @return []
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'drpay_dropin' => [
                    'mage_locale' => $this->_scopeConfig->getValue(
                        'general/locale/code',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                        $this->checkoutSession->getQuote()->getStore()
                    ),
                    'public_key' => $this->_enc->decrypt(
                        $this->_scopeConfig->getValue(
                            'dr_settings/config/drapi_public_key',
                            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                            $this->checkoutSession->getQuote()->getStore()
                        )
                    ),
                    'is_active' => true,
                    'title' => $this->_scopeConfig->getValue(
                        'payment/drpay_dropin/title',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                        $this->checkoutSession->getQuote()->getStore()
                    ),
                    'default_selling_entity' => $this->config->getDefaultSellingEntity()
                ],
            ],
        ];
        $config['payment']['instructions'][$this->_methodCode] = $this->getInstructions($this->_methodCode);
        return $config;
    }
    /**
     * Get instructions text from config
     *
     * @param  string $code
     * @return string
     */
    protected function getInstructions($code)
    {
        return nl2br($this->escaper->escapeHtml($this->_method->getInstructions()));
    }
}
