<?php

namespace BeeBots\QuickOrderForm\Block;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class ItemData extends Template
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * ItemSearch constructor.
     *
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param FormKey $formKey
     * @param Session $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        FormKey $formKey,
        Session $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
        $this->formKey = $formKey;
        $this->customerSession = $customerSession;
    }

    /**
     * Function: getSimpleProductJson
     *
     * @return false|array
     */
    public function getSimpleProducts()
    {
        $productCollection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter(
                'type_id',
                [
                    'in',
                    ['simple']
                ]
            )
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);

        $items = [];
        /** @var ProductInterface $product */
        foreach ($productCollection as $product) {
            $item = [
                'searchField' => $product->getSku() . ' ' . $product->getName(),
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'size' => $product->getProductSize() ?? '',
                'flavor' => $product->getFlavor() ? $product->getAttributeText('flavor') : '',
                'retailPrice' => $product->getPrice(),
                'tierPrice' => $this->getTierPrice($product),
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Function: getCacheLifetime
     *
     * @return bool|float|int|null
     */
    protected function getCacheLifetime()
    {
        // 1 month in seconds
        return 60 * 60 * 24 * 30;
    }

    /**
     * Function: getCacheKeyInfo
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCacheKeyInfo()
    {
        return [
            $this->getNameInLayout(),
            $this->_storeManager->getStore()->getCode(),
            $this->_storeManager->getStore()->getCode(),
        ];
    }

    /**
     * @param ProductInterface $product
     *
     * @return float|mixed|null
     */
    private function getTierPrice(ProductInterface $product)
    {
        $tierPrices = $product->getTierPrices();

        $customerGroupId = $this->getCustomerGroupId();

        $customerGroupPrices = [];
        foreach ($tierPrices as $tierPrice) {
            // Limit to the first tier (quantity 1)
            if ((int)$tierPrice->getQty() !== 1) {
                continue;
            }
            $customerGroupPrices[$tierPrice->getCustomerGroupId()] = $tierPrice->getValue();
        }

        return isset($customerGroupPrices[$customerGroupId])
            ? $customerGroupPrices[$customerGroupId]
            : $product->getPrice();
    }

    public function getJsLayout()
    {
        $jsLayout = parent::getJsLayout();
        $decodedJsLayout = json_decode($jsLayout, true);
        $decodedJsLayout['components']['beebots-quick-order-form']['post_url'] = $this->getFormPostUrl();
        $decodedJsLayout['components']['beebots-quick-order-form']['form_key'] = $this->formKey->getFormKey();
        $decodedJsLayout['components']['beebots-quick-order-form']['product_data'] = $this->getSimpleProducts();
        return json_encode($decodedJsLayout);
    }

    public function getFormPostUrl()
    {
        return $this->escapeUrl($this->getUrl('quick-order/cart/add'));
    }

    /**
     * @return int
     */
    private function getCustomerGroupId()
    {
        try {
            return $this->customerSession->getCustomerGroupId();
        } catch (Exception $e) {
            return 0;
        }
    }
}
