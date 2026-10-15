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
use StockAlert\Exception\SubscriptionRefusedException;
use StockAlert\Form\StockAlertSubscribe;
use StockAlert\Service\StockAlertService;
use StockAlert\StockAlert;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class RestockingAlertFrontOfficeController
 * @package StockAlert\Controller
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertFrontOfficeController extends BaseFrontController
{
    /**
     * @Route("/subscribe", name="_subscribe", methods="POST")
     * @throws \JsonException
     */
    #[Route('/module/stockalert', name: 'stockalert_front')]
    public function subscribe(Request $request, StockAlertService $stockAlertService): Response|RedirectResponse
    {
        $success = true;

        $form = $this->createForm(StockAlertSubscribe::getName(), FormType::class, [], ['csrf_protection' => false]);

        try {
            $subscribeForm = $this->validateForm($form)->getData();
            $message = $stockAlertService->subscribe($subscribeForm);
        } catch (SubscriptionRefusedException|FormValidationException $e) {
            // Messages written for the visitor: a refusal of a listener, the errors of the form.
            $success = false;
            $message = $e->getMessage();
        } catch (\Throwable $e) {
            // The raw message may carry SQL, paths or an address: the class goes to the log, nothing to the visitor.
            Tlog::getInstance()->error('Stock alert: a subscription failed ('.$e::class.')');
            $success = false;
            $message = Translator::getInstance()->trans('Something went wrong. Please try again later.', [], 'stockalert.fo.default');
        }

        if (!$request->isXmlHttpRequest()) {
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->set('flashMessage', $message);
            }
            // The form is an array: InputBag::get() refuses it with a 400, the whole bag is read instead.
            $subscribeFormData = $request->attributes->get('stockalert_subscribe_form')
                ?? $request->query->all()['stockalert_subscribe_form']
                ?? $request->request->all()['stockalert_subscribe_form']
                ?? null;

            return new RedirectResponse($this->safeSuccessUrl($request, $subscribeFormData));
        }

        return $this->jsonResponse(
            json_encode([
                "success" => $success,
                "message" => $message
            ], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * The page the visitor comes back to: the success url of the form when it is an address of this shop, else the
     * home page. A forged request carries no form or no address, and the field is the visitor's to write.
     */
    private function safeSuccessUrl(Request $request, mixed $formData): string
    {
        $url = \is_array($formData) ? ($formData['success_url'] ?? null) : null;

        if (!\is_string($url) || '' === $url) {
            return '/';
        }

        $parts = parse_url($url);

        if (false === $parts) {
            return '/';
        }

        // A path of the shop, or an absolute address on the host of the request: nothing else.
        if (!isset($parts['host'])) {
            return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : '/';
        }

        return strtolower($parts['host']) === strtolower($request->getHost()) ? $url : '/';
    }
}
