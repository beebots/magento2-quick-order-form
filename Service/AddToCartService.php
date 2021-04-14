<?php

namespace BeeBots\QuickOrderForm\Service;

use BeeBots\QuickOrderForm\Model\Configuration;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AddToCartService
{

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var StockRegistry
     */
    private $stockRegistry;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * AddToCartService constructor.
     *
     * @param ProductRepository $productRepository
     * @param Configurable $configurable
     * @param Product $productResource
     * @param StockRegistry $stockRegistry
     * @param Cart $cart
     * @param Session $checkoutSession
     */
    public function __construct(
        ProductRepository $productRepository,
        StockRegistry $stockRegistry,
        Cart $cart,
        Session $checkoutSession
    ) {
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @param array $ids
     * @param array $quantities
     * @param bool $resetTotalsCollectedFlag helpful if form is exposed on checkout
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function addProducts(array $ids, array $quantities, bool $resetTotalsCollectedFlag = false): array
    {
        $result = [];
        $productsAdded = false;
        $quote = $this->checkoutSession->getQuote();

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

                $configuration = new Configuration();
                $configuration->setData('qty', $qty);
                //add a cheap indicator for tracking
                $configuration->setData('used_quick_order', true);

                //if they are the first items in the cart lets add them. If not, add them to the quote.
                if (! $quote->getItems()) {
                    $this->cart->addProduct($product, $configuration);
                    $productsAdded = true;
                } else {
                    $quote->addProduct($product, $configuration);
                }
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

        if ($resetTotalsCollectedFlag) {
            //if quick order is exposed on checkout, saving before other updates
            //can prevent totals on existing cart items from being recalculated.
            //Reset if needed.
            $quote->setTotalsCollectedFlag(false);
        }

        return $result;
    }
}
