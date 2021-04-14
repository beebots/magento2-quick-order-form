<?php

namespace BeeBots\QuickOrderForm\Block;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class QuickOrder extends Template
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


    public function getJsLayout()
    {
        $jsLayout = parent::getJsLayout();
        $decodedJsLayout = json_decode($jsLayout, true);
        $decodedJsLayout['components']['beebots-quick-order-form']['post_url'] = $this->getFormPostUrl();
        $decodedJsLayout['components']['beebots-quick-order-form']['form_key'] = $this->formKey->getFormKey();
        $decodedJsLayout['components']['beebots-quick-order-form']['product_data'] = json_decode(
            $this->getChildBlock('quick.order.item.data.alias')->toHtml(),
            true
        );
        return json_encode($decodedJsLayout);
    }

    public function getFormPostUrl()
    {
        return $this->escapeUrl($this->getUrl('quick-order/cart/add'));
    }

}
