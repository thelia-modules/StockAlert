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


use StockAlert\Form\StockAlertConfig;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Tools\URL;

/**
 * @Route("/admin/module/stockalert", name="stockalert_back")
 * Class StockAlertBackOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <julien@thelia.net>
 */
class StockAlertBackOfficeController extends BaseAdminController
{

    /**
     * @Route("/configuration", name="_configuration", methods="POST")
     */
    public function configuration(ParserContext $parserContext)
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
            "module-configure",
            [
                "module_code" => StockAlert::getModuleCode()
            ]
        );
    }

    /**
     * @Route("/delete", name="_delete", methods="GET")
     */
    public function deleteEmail(RequestStack $requestStack, Session $session)
    {
        $restockingAlertId = $requestStack->getCurrentRequest()->get("id");
        if ($restockingAlertId) {
            $restockingAlert = RestockingAlertQuery::create()->filterById($restockingAlertId)->findOne();
            $restockingAlert->delete();
        }
        return new RedirectResponse(URL::getInstance()->absoluteUrl($session->getReturnToUrl()));
    }
}
