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
use StockAlert\Form\StockAlertSubscribe;
use StockAlert\StockAlert;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/module/stockalert", name="stockalert_front")
 * Class RestockingAlertFrontOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertFrontOfficeController extends BaseFrontController
{

    /**
     * @Route("/subscribe", name="_subscribe", methods="POST")
     */
    public function subscribe(EventDispatcherInterface $eventDispatcher, RequestStack $requestStack)
    {
        $success = true;

        $form = $this->createForm(StockAlertSubscribe::getName(), FormType::class, [], ['csrf_protection'   => false]);

        try {
            $subscribeForm = $this->validateForm($form)->getData();

            $subscriberEvent = new StockAlertEvent(
                $subscribeForm['product_sale_elements_id'],
                $subscribeForm['email'],
                $subscribeForm['newsletter'],
                $requestStack->getCurrentRequest()->getSession()->getLang()->getLocale()
            );

            $eventDispatcher->dispatch($subscriberEvent, StockAlertEvents::STOCK_ALERT_SUBSCRIBE);

            $message = Translator::getInstance()->trans(
                "C’est noté ! Vous recevrez un e-mail dès que le produit sera de nouveau en stock.",
                [],
                StockAlert::MESSAGE_DOMAIN
            );
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        if (!$requestStack->getCurrentRequest()->isXmlHttpRequest()) {
            $requestStack->getCurrentRequest()->getSession()->getFlashBag()->set('flashMessage', $message);
            return RedirectResponse::create($requestStack->getCurrentRequest()->get('stockalert_subscribe_form')['success_url']);
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
