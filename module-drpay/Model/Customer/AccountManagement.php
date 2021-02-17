<?php

namespace Digitalriver\DrPay\Model\Customer;

class AccountManagement extends \Magento\Customer\Model\AccountManagement
{
    public function isEmailAvailable($customerEmail, $websiteId = null)
    {
        $objectManager   =  \Magento\Framework\App\ObjectManager::getInstance();
        $checkoutSession = $objectManager->get(\Magento\Checkout\Model\Session::class);
        $checkoutSession->setGuestCustomerEmail($customerEmail);

        return parent::isEmailAvailable($customerEmail, $websiteId);
    }
}
