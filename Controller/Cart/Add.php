<?php


namespace BeeBots\QuickOrderForm\Controller\Cart;


use BeeBots\QuickOrderForm\Service\AddToCartService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;

class Add extends Action
{
    /** @var RequestInterface|Http */
    private $request;

    /**
     * @var AddToCartService
     */
    private $addToCartService;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var Validator
     */
    private $formKeyValidator;

    /**
     * @var Json
     */
    private $json;

    /**
     * Add constructor.
     *
     * @param Context $context
     * @param RequestInterface $request
     * @param AddToCartService $addToCartService
     * @param RedirectFactory $redirectFactory
     * @param Validator $formKeyValidator
     * @param Json $json
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        AddToCartService $addToCartService,
        RedirectFactory $redirectFactory,
        Validator $formKeyValidator,
        Json $json
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->addToCartService = $addToCartService;
        $this->redirectFactory = $redirectFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->json = $json;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        if (! $this->request->isPost()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('*');
            return $redirect;
        }

        if (! $this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Application form has expired. Please try again.'));
            return $this->json->setData(['client_error' => 'Application form has expired. Please try again.']);
        }

        $ids = $this->request->getParam('product-selector');
        $quantities = $this->request->getParam('qty');

        try {
            $result = $this->addToCartService->addProducts($ids, $quantities);

            if (count($result) === 0) {
                $this->messageManager->addSuccessMessage(__('Items were added successfully'));
                return $this->json->setData(['success' => 'Items were added successfully']);
            }
            if (array_key_exists('invalid_qty_for_skus', $result['client_errors'])) {
                $this->messageManager->addErrorMessage(
                    __(
                        'The following skus have an invalid quantity: ' . implode(
                            ', ',
                            $result['client_errors']['invalid_qty_for_skus']
                        )
                    )
                );
            }
            if (array_key_exists('missing_sku', $result['client_errors'])) {
                $this->messageManager->addErrorMessage(
                    __(
                        'Missing skus for the following rows: ' . implode(
                            ', ',
                            $this->getRows($result['client_errors']['missing_sku'])
                        )
                    )
                );
            }
            if (array_key_exists('invalid_skus', $result['client_errors'])) {
                $this->messageManager->addErrorMessage(
                    __(
                        'The following skus are invalid: ' . implode(
                            ', ',
                            $result['client_errors']['invalid_skus']
                        )
                    )
                );
            }
            if (array_key_exists('localized_exception', $result['client_errors'])) {
                foreach ($result['client_errors']['localized_exception'] as $exceptionMessage) {
                    $this->messageManager->addErrorMessage(
                        __($exceptionMessage)
                    );
                }
            }
            return $this->json->setData($result);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->json->setData(['client_errors' => ['localized_exception' => $e->getMessage()]]);
        }
    }

    private function getRows(array $zeroBasedRowIndices): array
    {
        $oneBasedIndices = [];
        foreach ($zeroBasedRowIndices as $zeroBasedRowIndex) {
            $oneBasedIndices[] = ++$zeroBasedRowIndex;
        }
        return $oneBasedIndices;
    }
}
