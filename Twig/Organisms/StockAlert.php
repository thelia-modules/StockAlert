<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StockAlert\Twig\Organisms;

use StockAlert\Exception\SubscriptionRefusedException;
use StockAlert\Service\StockAlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\Form\FormServiceInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * The restocking alert form of a sale element out of stock.
 *
 * What the visitor reads when something goes wrong is never an exception message, except the one of a
 * SubscriptionRefusedException (a refusal a listener wrote for them): any other failure is logged and shown
 * as a generic message. After a subscription the component says so, unless the page asked not to
 * (`showSuccessMessage: false`): it then renders nothing and dispatches the browser event
 * `stockalert:subscribed`, for a page that closes its own window.
 */
#[AsLiveComponent(name: 'StockAlert', template: '@StockAlertModule/components/StockAlert.html.twig')]
class StockAlert extends AbstractController
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(updateFromParent: true)]
    public ?int $pseId = null;

    #[LiveProp]
    public bool $success = false;

    #[LiveProp]
    public ?string $message = null;

    #[LiveProp]
    public bool $showSuccessMessage = true;

    public function __construct(
        private readonly FormServiceInterface $formService,
        private StockAlertService $stockAlertService,
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        $form = $this->formService->getFormByName('stockalert_subscribe_form', [
            'product_sale_elements_id' => $this->pseId,
        ]);

        return $form;
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->dispatchBrowserEvent('modal:open');
    }

    #[LiveAction]
    public function save(): void
    {
        try {
            $this->submitForm();
            if ($this->getForm()->isSubmitted() && $this->getForm()->isValid()) {
                $data = $this->getForm()->getData();
                // The form keeps the sale element it was built with; the parent page may
                // have switched variant since, and the live prop is what follows it.
                $data['product_sale_elements_id'] = $this->pseId ?? $data['product_sale_elements_id'];
                $this->message = $this->stockAlertService->subscribe($data);
                $this->success = true;
                $this->dispatchBrowserEvent('stockalert:subscribed', ['pseId' => $this->pseId]);
            } else {
                $this->success = false;
                $this->message = $this->formErrorMessage();
            }
        } catch (SubscriptionRefusedException $refused) {
            $this->success = false;
            $this->message = $refused->getMessage();
        } catch (UnprocessableEntityHttpException) {
            // submitForm() throws when the form is invalid, to have the page re-rendered with its errors:
            // here they are shown by the component itself.
            $this->success = false;
            $this->message = $this->formErrorMessage();
        } catch (\Throwable $exception) {
            // The raw message may carry SQL, paths or an address: the class goes to the log, nothing to the visitor.
            Tlog::getInstance()->error('Stock alert: a subscription failed ('.$exception::class.')');
            $this->success = false;
            $this->message = Translator::getInstance()->trans('Something went wrong. Please try again later.', [], 'stockalert.fo.default');
        }
    }

    /**
     * A wrong address has the module's own message; any other refusal of the form (a robot check that
     * failed, a field a theme added) its first message, which the form writes for the visitor.
     */
    private function formErrorMessage(): string
    {
        $form = $this->getForm();

        if (0 === $form->get('email')->getErrors()->count()) {
            foreach ($form->getErrors(true) as $error) {
                return $error->getMessage();
            }
        }

        return Translator::getInstance()->trans('Please enter a valid email address.', [], 'stockalert.fo.default');
    }
}
