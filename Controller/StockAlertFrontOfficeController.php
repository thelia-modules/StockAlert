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

use StockAlert\Form\StockAlertSubscribe;
use StockAlert\Service\StockAlertSubscriptionService;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: "/module/stockalert", name: "stockalert_front")]
class StockAlertFrontOfficeController extends BaseFrontController
{
    /**
     * @param StockAlertSubscriptionService $subscriptionService
     * @param RequestStack $requestStack
     * @return Response|RedirectResponse
     * @throws \JsonException
     */
    #[Route(path: "/subscribe", name: "_subscribe", methods: ["POST"])]
    public function subscribe(
        StockAlertSubscriptionService $subscriptionService,
        RequestStack $requestStack
    ): Response|RedirectResponse
    {
        $request = $requestStack->getCurrentRequest();

        $form = $this->createForm(
            StockAlertSubscribe::getName(),
            FormType::class,
            [],
            ['csrf_protection' => false]
        );

        $data = [];
        $success = true;

        try {
            $data = $this->validateForm($form)->getData();

            $result = $subscriptionService->subscribe(
                $data['product_sale_elements_id'],
                $data['email'],
                $data['newsletter'] ?? false
            );
            $success = $result['success'];
            $message = $result['message'];
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        if (!$request?->isXmlHttpRequest()) {
            $request?->getSession()
                ->getFlashBag()
                ->set('flashMessage', $message);

            $redirectUrl = $data['success_url'] ?? $this->generateUrl('homepage');
            return new RedirectResponse($redirectUrl);
        }

        return $this->jsonResponse(json_encode([
            'success' => $success,
            'message' => $message,
        ], JSON_THROW_ON_ERROR));
    }
}