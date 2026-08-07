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

namespace StockAlert\Controller;

use StockAlert\Form\StockAlertConfig;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;

/**
 * Class StockAlertBackOfficeController.
 *
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
#[Route('/admin/modules/StockAlert', name: 'stockalert.config')]
class StockAlertBackOfficeController extends BaseAdminController
{
    #[Route('/save', name: '.save', methods: ['POST'])]
    public function configuration(ParserContext $parserContext): RedirectResponse
    {
        $errorMessage = null;

        $form = $this->createForm(StockAlertConfig::getName());

        try {
            $configForm = $this->validateForm($form)->getData();

            ConfigQuery::write(StockAlert::CONFIG_ENABLED, $configForm['enabled']);
            ConfigQuery::write(StockAlert::CONFIG_THRESHOLD, $configForm['threshold']);
            $emails = str_replace(' ', '', $configForm['emails']);
            ConfigQuery::write(StockAlert::CONFIG_EMAILS, $emails);
            ConfigQuery::write(StockAlert::CONFIG_NOTIFY, $configForm['notify']);
        } catch (FormValidationException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if (null !== $errorMessage) {
            $form->setErrorMessage($errorMessage);

            $parserContext
                ->addForm($form)
                ->setGeneralError($errorMessage);
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/'.StockAlert::getModuleCode())
        );
    }

    #[Route('/delete', name: '.delete', methods: ['POST'])]
    public function deleteEmail(RequestStack $requestStack, Session $session, TokenProvider $tokenProvider): RedirectResponse
    {
        $request = $requestStack->getCurrentRequest();

        $tokenProvider->checkToken((string) $request->query->get('_token'));

        $restockingAlertId = $request->query->get('id');
        if ($restockingAlertId) {
            $restockingAlert = RestockingAlertQuery::create()->filterById($restockingAlertId)->findOne();
            if (null !== $restockingAlert) {
                $restockingAlert->delete();
            }
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/'.StockAlert::getModuleCode())
        );
    }
}
