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
use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class RestockingAlertFrontOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <jchanseaume@openstudio.fr>
 */
class StockAlertFrontOfficeController extends BaseFrontController
{

    public function subscribe()
    {
        $errorMessage = null;

        $form = $this->createForm('stockalert.subscribe.form', 'form');

        try {
            $subscribeForm = $this->validateForm($form)->getData();

            $subscriberEvent = new StockAlertEvent(
                $subscribeForm['product_sale_elements_id'],
                $subscribeForm['email'],
                $this->getRequest()->getSession()->getLang()->getLocale()
            );

            $this->dispatch(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $subscriberEvent);

            return $this->jsonResponse(
                json_encode(
                    [
                        "success" => true,
                        "message" => $this->getTranslator()->trans(
                            "Your request has been taken into account",
                            [],
                            StockAlert::MESSAGE_DOMAIN
                        )
                    ]
                )
            );
        } catch (FormValidationException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        return $this->jsonResponse(
            json_encode(
                [
                    "success" => false,
                    "message" => $errorMessage
                ]
            )
        );
    }
}
