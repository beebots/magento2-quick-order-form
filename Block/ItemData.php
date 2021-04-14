<?php

namespace BeeBots\QuickOrderForm\Block;

use Banyan\Utilities\Cache\CacheKeyGenerator;
use Banyan\Utilities\Cache\CacheLifetime;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;

class ItemData extends Template
{
    use CacheLifetime;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var CacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * ItemSearch constructor.
     *
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Session $customerSession
     * @param CacheKeyGenerator $cacheKeyGenerator
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        Session $customerSession,
        CacheKeyGenerator $cacheKeyGenerator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
        $this->customerSession = $customerSession;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
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
        /** @var ProductInterface|Product $product */
        foreach ($productCollection as $product) {
            if ($attribute = $product->getAttributeText('unavailable_for_purchase')) {
                if ($attribute->getText() === 'Yes') {
                    continue;
                }
            }

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
     * Function: getCacheKeyInfo
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return $this->cacheKeyGenerator->getKey(
            $this->getNameInLayout(),
            true
        );
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

    public function _toHtml()
    {
        return json_encode($this->getSimpleProducts());
    }
}
