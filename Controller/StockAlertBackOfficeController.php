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


use StockAlert\StockAlert;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;

/**
 * Class StockAlertBackOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <jchanseaume@openstudio.fr>
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
            ConfigQuery::write(StockAlert::CONFIG_EMAILS, $configForm['emails']);

            return $this->generateSuccessRedirect($form);

        } catch (FormValidationException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        $form->setErrorMessage($errorMessage);

        $this->getParserContext()
            ->addForm($form)
            ->setGeneralError($errorMessage)
        ;

        return $this->render(
            "module-configure",
            [
                "module_code" => StockAlert::getModuleCode()
            ]
        );

    }
}
