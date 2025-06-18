<?php

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
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use TwigEngine\Service\FormService;

#[AsLiveComponent(name: 'StockAlert', template: '@StockAlertModule/components/StockAlert.html.twig')]
class StockAlert extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public ?int $pseId = null;
    public bool $success = false;
    public ?string $message = null;


    public function __construct(private readonly FormService $formService, private StockAlertService $stockAlertService
    )
    {
    }

    protected function instantiateForm(): FormInterface
    {
        $form = $this->formService->getFormByName('stockalert_subscribe_form');

        return $form;
    }

    #[LiveAction]
    public function save(): void
    {
        try {
            $this->submitForm();
            if ($this->getForm()->isSubmitted() && $this->getForm()->isValid()) {
                $data = $this->getForm()->getData();
                $this->message = $this->stockAlertService->subscribe($data);
                $this->success = true;
            } else {
                $this->success = false;
                $this->message = 'Erreur : Le formulaire est invalide.';
            }
        } catch (\Throwable $th) {
            $this->success = false;
            $this->message = 'Erreur lors de la soumission';
        }
    }
}
