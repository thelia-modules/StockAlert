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

use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\StockAlert;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Form\Exception\FormValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class RestockingAlertFrontOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertFrontOfficeController extends BaseFrontController
{

    public function subscribe()
    {
        $success = true;

        $form = $this->createForm('stockalert.subscribe.form', 'form', [], ['csrf_protection'   => false]);

        try {
            $subscribeForm = $this->validateForm($form)->getData();

            $subscriberEvent = new StockAlertEvent(
                $subscribeForm['product_sale_elements_id'],
                $subscribeForm['email'],
                $subscribeForm['newsletter'],
                $this->getRequest()->getSession()->getLang()->getLocale()
            );

            $this->dispatch(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $subscriberEvent);

            $message = $this->getTranslator()->trans(
                "C’est noté ! Vous recevrez un e-mail dès que le produit sera de nouveau en stock.",
                [],
                StockAlert::MESSAGE_DOMAIN
            );
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        if (!$this->getRequest()->isXmlHttpRequest()) {
            $this->getSession()->getFlashBag()->set('flashMessage', $message);
            return RedirectResponse::create($this->getRequest()->get('stockalert_subscribe_form')['success_url']);
        }

        return $this->jsonResponse(
            json_encode(
                [
                    "success" => $success,
                    "message" => $message
                ]
            )
        );
    }
}
