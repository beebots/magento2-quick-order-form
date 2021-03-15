<?php


namespace BeeBots\QuickOrderForm\Model;


use Magento\Framework\App\Config\ScopeConfigInterface;

class QuickOrderConfig
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /**
     * LayoutProcessor constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Function: getApiKey
     */
    public function isStandAloneEnabled()
    {
        return $this->scopeConfig->getValue('beebots/quick_order_form/enabled');
    }

    /**
     * Function: isAutocompleteEnabled
     *
     * @return mixed
     */
    public function isCheckoutEnabled()
    {
        return $this->scopeConfig->getValue('beebots/quick_order_form/enabled_on_checkout');
    }
}
