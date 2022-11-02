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


use StockAlert\Model\RestockingAlert;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ExportQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * Class StockAlertBackOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <julien@thelia.net>
 */
class StockAlertBackOfficeController extends BaseAdminController
{

    public function configuration()
    {
        $errorMessage = null;

        $form = $this->createForm('stockalert.configuration.form', 'form');

        try {
            $configForm = $this->validateForm($form)->getData();

            ConfigQuery::write(StockAlert::CONFIG_ENABLED, $configForm['enabled']);
            ConfigQuery::write(StockAlert::CONFIG_THRESHOLD, $configForm['threshold']);
            $emails = str_replace(' ', '', $configForm['emails']);
            ConfigQuery::write(StockAlert::CONFIG_EMAILS, $emails);

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        $form->setErrorMessage($errorMessage);

        $this->getParserContext()
            ->addForm($form)
            ->setGeneralError($errorMessage);

        return $this->render(
            "module-configure",
            [
                "module_code" => StockAlert::getModuleCode(),
            ]
        );
    }

    public function deleteEmail()
    {
        $restockingAlertId = $this->getRequest()->get("id");
        if ($restockingAlertId) {
            $restockingAlert = RestockingAlertQuery::create()->filterById($restockingAlertId)->findOne();
            $restockingAlert->delete();
        }
        return new RedirectResponse(URL::getInstance()->absoluteUrl($this->getSession()->getReturnToUrl()));
    }
}
