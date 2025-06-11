<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace StockAlert\Controller;

use Propel\Runtime\Exception\PropelException;
use StockAlert\Form\StockAlertConfig;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Tools\URL;

#[Route(path: '/admin/module/stockalert', name: 'stockalert_back')]
class StockAlertBackOfficeController extends BaseAdminController
{
    /**
     * @param ParserContext $parserContext
     * @return Response|RedirectResponse|string|\Symfony\Component\HttpFoundation\Response|null
     */
    #[Route(path: '/configuration', name: '_configuration', methods: ['POST'])]
    public function configuration(ParserContext $parserContext): Response|RedirectResponse|string|\Symfony\Component\HttpFoundation\Response|null
    {
        $errorMessage = null;
        $form = $this->createForm(StockAlertConfig::getName());

        try {
            $configForm = $this->validateForm($form)->getData();

            ConfigQuery::write(StockAlert::CONFIG_ENABLED,   $configForm['enabled']);
            ConfigQuery::write(StockAlert::CONFIG_THRESHOLD, $configForm['threshold']);
            $emails = str_replace(' ', '', $configForm['emails']);
            ConfigQuery::write(StockAlert::CONFIG_EMAILS,    $emails);
            ConfigQuery::write(StockAlert::CONFIG_NOTIFY,    $configForm['notify']);

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        $form->setErrorMessage($errorMessage);

        $parserContext
            ->addForm($form)
            ->setGeneralError($errorMessage);

        return $this->render(
            'module-configure',
            [
                'module_code' => StockAlert::getModuleCode(),
            ]
        );
    }

    /**
     * @param RequestStack $requestStack
     * @param Session $session
     * @return RedirectResponse
     * @throws PropelException
     */
    #[Route(path: '/delete', name: '_delete', methods: ['GET'])]
    public function deleteEmail(RequestStack $requestStack, Session $session): RedirectResponse
    {
        $restockingAlertId = $requestStack->getCurrentRequest()->get('id');

        if ($restockingAlertId) {
            $restockingAlert = RestockingAlertQuery::create()
                ->filterById($restockingAlertId)
                ->findOne();

            if (null !== $restockingAlert) {
                $restockingAlert->delete();
            }
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl($session->getReturnToUrl())
        );
    }
}
