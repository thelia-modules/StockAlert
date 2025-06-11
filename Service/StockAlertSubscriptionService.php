<?php

namespace StockAlert\Service;

use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Translation\Translator;

class StockAlertSubscriptionService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
    ) {}

    /**
     * Gère la souscription à une alerte stock
     */
    public function subscribe($pseId, $email, $newsletter = false): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->getSession()?->getLang()?->getLocale() ?? 'fr_FR';

        $success = true;

        try {
            $event = new StockAlertEvent(
                $pseId,
                $email,
                $newsletter,
                $locale
            );

            $this->eventDispatcher->dispatch($event, StockAlertEvents::STOCK_ALERT_SUBSCRIBE);

            $message = Translator::getInstance()->trans(
                "C’est noté ! Vous recevrez un e-mail dès que le produit sera de nouveau en stock.",
                [],
                StockAlert::MESSAGE_DOMAIN
            );
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }
}
