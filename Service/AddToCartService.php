<?php

namespace BeeBots\QuickOrderForm\Service;

use BeeBots\QuickOrderForm\Model\Configuration;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Checkout\Model\Cart;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AddToCartService
{

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Configurable
     */
    private $configurable;

    /**
     * @var Product
     */
    private $productResource;

    /**
     * @var StockRegistry
     */
    private $stockRegistry;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * AddToCartService constructor.
     *
     * @param ProductRepository $productRepository
     * @param Configurable $configurable
     * @param Product $productResource
     * @param StockRegistry $stockRegistry
     * @param Cart $cart
     */
    public function __construct(
        ProductRepository $productRepository,
        Configurable $configurable,
        Product $productResource,
        StockRegistry $stockRegistry,
        Cart $cart
    ) {
        $this->productRepository = $productRepository;
        $this->configurable = $configurable;
        $this->productResource = $productResource;
        $this->stockRegistry = $stockRegistry;
        $this->cart = $cart;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @param array $ids
     * @param array $quantities
     * @return array
     */
    public function addProducts(array $ids, array $quantities): array
    {
        $result = [];
        $productsAdded = false;

        // make sure product type is simple
        foreach ($ids as $index => $id) {
            $qty = $quantities[$index];
            if (! $id || ! $qty) {
                $result['client_errors']['missing_fields'][] = 'Both item and qty are required.';
                continue;
            }
            try {
                $product = $this->productRepository->getById($id, false);
                $stockItem = $this->stockRegistry->getStockItem($id);

                if (! $stockItem->getIsInStock()) {
                    $result['client_errors']['out_of_stock'][] =
                        "{$product->getName()} ({$product->getSku()}) is out of stock.";
                    continue;
                }

                if ($product->getTypeId() !== Type::TYPE_SIMPLE) {
                    $result['client_errors']['invalid_skus'][] = $product->getSku();
                    continue;
                }
                $configurable = $this->getConfigurableProductFromSimple($product->getEntityId());
                if ($configurable) {
                    $configuration = $this->getProductConfigurationOfConfigurableFromSimple($configurable, $product);
                    $configuration->setData('qty', $qty);
                    $this->cart->addProduct($configurable, $configuration);
                } else {
                    $this->cart->addProduct($product, $qty);
                }
                $productsAdded = true;
            } catch (NoSuchEntityException $e) {
                $result['client_errors']['invalid_skus'][] = "Invalid Sku for id: {$id}";
            } catch (LocalizedException $e) {
                if ($e->getMessage() !== 'You need to choose options for your item.') {
                    $result['client_errors']['localized_exception'][] = $e->getMessage();
                }
                $result['client_errors']['invalid_skus'][] = "Invalid Sku for id: {$id}";
            }
        }
        if (! array_key_exists('client_errors', $result) && $productsAdded) {
            $this->cart->save();
        }
        return $result;
    }

    /**
     * @param $simpleProductId
     * @return bool|ProductInterface|mixed
     * @throws NoSuchEntityException
     */
    private function getConfigurableProductFromSimple($simpleProductId)
    {
        $parentIds = $this->configurable->getParentIdsByChild($simpleProductId);
        if ($parentIds && array_key_exists(0, $parentIds)) {
            return $this->productRepository->getById($parentIds[0], false, null, true);
        }
        return false;
    }

    private function getProductConfigurationOfConfigurableFromSimple(
        ProductInterface $parentProduct,
        ProductInterface $product
    ) {
        /** @var Configurable $configurable */
        /** @noinspection PhpUndefinedMethodInspection */
        $configurable = $parentProduct->getTypeInstance();
        /** @noinspection PhpParamsInspection */
        $attributes = $configurable->getConfigurableAttributesAsArray($parentProduct);
        $configuration = new Configuration();
        foreach ($attributes as $key => $attribute) {
            /** @noinspection PhpUndefinedMethodInspection */
            $configuration->setData('super_attribute', [$key => $product->getData($attribute['attribute_code'])]);
        }
        return $configuration;
    }
}
