<?php

namespace BeeBots\QuickOrderForm\Controller\Index;

use BeeBots\QuickOrderForm\Model\QuickOrderConfig;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /** @var PageFactory */
    private $pageFactory;


    /**
     * Index constructor.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(Context $context, PageFactory $pageFactory)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return ResultInterface|ResponseInterface
     * @throws NotFoundException
     */
    public function execute()
    {
        /** @var QuickOrderConfig $quickOrderConfig */
        $quickOrderConfig = $this->_objectManager
            ->get(QuickOrderConfig::class);

        if (! $quickOrderConfig->isStandAloneEnabled()) {
            throw new NotFoundException(__("Page not found"));
        }

        return $this->pageFactory->create();
    }
}
