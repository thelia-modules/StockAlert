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

use StockAlert\Service\StockAlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\Form\FormServiceInterface;
use Thelia\Core\Translation\Translator;

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
            } else {
                $this->success = false;
                $this->message = Translator::getInstance()->trans('Please enter a valid email address.', [], 'stockalert.fo.default');
            }
        } catch (\Throwable) {
            $this->success = false;
            $this->message = Translator::getInstance()->trans('Something went wrong. Please try again later.', [], 'stockalert.fo.default');
        }
    }
}
