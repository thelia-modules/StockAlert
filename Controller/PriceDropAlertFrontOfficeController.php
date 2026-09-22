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

use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\PriceDrop\PriceDropSubscriptionService;
use StockAlert\PriceDrop\UnsubscribeTokenSigner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\LangQuery;

/**
 * The unsubscribe link from a confirmation: no account, no session needed. The
 * page answers the same way whether the subscription still existed, had already
 * been removed, or the token was made up: a link must never confirm an address.
 */
final class PriceDropAlertFrontOfficeController extends BaseFrontController
{
    #[Route('/module/stockalert/price-drop/unsubscribe/{token}', name: 'stockalert_price_drop_unsubscribe', methods: ['GET'])]
    public function unsubscribe(
        string $token,
        Request $request,
        UnsubscribeTokenSigner $tokenSigner,
        PriceDropSubscriptionService $subscriptionService,
    ): Response {
        // Two steps for every token, valid or not: the first one acts and picks the
        // language, the second one renders in it. One round trip, identical from outside.
        if ($request->query->has('done')) {
            return $this->render('price-drop-unsubscribed.html.twig');
        }

        $priceDropAlertId = $tokenSigner->verify($token);

        if (null !== $priceDropAlertId) {
            $this->answerInTheSubscriberLanguage($request, PriceDropAlertQuery::create()->findPk($priceDropAlertId));
            $subscriptionService->unsubscribe($priceDropAlertId);
        }

        return new RedirectResponse($request->getPathInfo().'?done=1');
    }

    /**
     * The link comes from a confirmation shown in the subscriber's language, with no
     * cookie to carry it: the language recorded on the subscription is put back on
     * the session before the page renders.
     */
    private function answerInTheSubscriberLanguage(Request $request, ?PriceDropAlert $alert): void
    {
        $lang = null !== $alert?->getLocale() ? LangQuery::create()->findOneByLocale($alert->getLocale()) : null;

        $session = $request->hasSession() ? $request->getSession() : null;

        if (null !== $lang && $session instanceof Session) {
            $session->setLang($lang);
        }
    }
}
