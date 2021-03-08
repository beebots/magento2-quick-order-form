<?php

namespace BeeBots\QuickOrderForm\Service;

use BeeBots\QuickOrderForm\Model\Configuration;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

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
     * @var \Magento\Quote\Model\QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * AddToCartService constructor.
     *
     * @param Session $checkoutSession
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param ProductRepository $productRepository
     * @param Configurable $configurable
     * @param Product $productResource
     * @param CartRepositoryInterface $cartRepository
     * @param Session $session
     */
    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        ProductRepository $productRepository,
        Configurable $configurable,
        Product $productResource,
        CartRepositoryInterface $cartRepository,
        Session $session
    ) {
        $this->productRepository = $productRepository;
        $this->configurable = $configurable;
        $this->productResource = $productResource;
        $this->quoteFactory = $quoteFactory;
        $this->cartRepository = $cartRepository;
        $this->session = $session;
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
        $cart = $this->getCart();

        // make sure product type is simple
        foreach ($ids as $index => $id) {
            $qty = $quantities[$index];
            if (! $id && ! $qty) {
                // skip when both values are falsy
                continue;
            }
            if ($id && ! $qty) {
                $result['client_errors']['invalid_qty_for_skus'][] = $id;
                continue;
            }
            if (! is_numeric($qty)) {
                $result['client_errors']['invalid_qty_for_skus'][] = $id;
                continue;
            }
            if (! $id && $qty) {
                $result['client_errors']['missing_sku'][] = $index;
                continue;
            }
            try {
                $product = $this->productRepository->getById($id, false);
                if ($product->getTypeId() !== Type::TYPE_SIMPLE) {
                    $result['client_errors']['invalid_skus'][] = $id;
                    continue;
                }
                $configurable = $this->getConfigurableProductFromSimple($product->getEntityId());
                if ($configurable) {
                    $configuration = $this->getProductConfigurationOfConfigurableFromSimple($configurable, $product);
                    $configuration->setData('qty', $qty);
                    $cart->addProduct($configurable, $configuration);
                } else {
                    $cart->addProduct($product, $qty);
                }
                $productsAdded = true;
            } catch (NoSuchEntityException $e) {
                $result['client_errors']['invalid_skus'][] = $id;
            } catch (LocalizedException $e) {
                if ($e->getMessage() !== 'You need to choose options for your item.') {
                    $result['client_errors']['localized_exception'][] = $e->getMessage();
                }
                $result['client_errors']['invalid_skus'][] = $id;
            }
        }
        if (! array_key_exists('client_errors', $result) && $productsAdded) {
            $this->cartRepository->save($cart);
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
            $configuration->setData('super_attribute',[$key => $product->getData($attribute['attribute_code'])]);
        }
        return $configuration;
    }

    private function getCart()
    {
        try {
            /** @var Quote $quote */
            $quote = $this->quoteFactory->create();
            $customerQuote = $this->cartRepository->getActiveForCustomer(
                $this->session->getCustomerId()
            );
            $cart = $quote->loadActive($customerQuote->getId());

        } catch (NoSuchEntityException $e) {
            $a=1;
            return;
        }

        return $cart;
    }
}
