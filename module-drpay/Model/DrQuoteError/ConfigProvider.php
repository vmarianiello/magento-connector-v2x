<?php
/**
 * Provides DR Quote Error details
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
declare(strict_types=1);

namespace Digitalriver\DrPay\Model\DrQuoteError;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class ConfigProvider implements ConfigProviderInterface
{
    const DR_QUOTE = 'drQuote';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * ConfigProvider constructor.
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     *  Sets the Dr Quote Error value in config provider
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            self::DR_QUOTE => [
                'dr_error_quote' => [
                   'is_dr_quote_error' => $this->checkoutSession->getDrQuoteError()
                ],
            ],
        ];
    }
}
