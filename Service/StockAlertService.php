<?php

namespace StockAlert\Service;

use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Translation\Translator;

readonly class StockAlertService
{

    public function __construct(private EventDispatcherInterface $eventDispatcher, private RequestStack $requestStack)
    {
    }

    public function subscribe(array $subscribeForm): string
    {
        $locale = 'fr_FR';
        if ($this->requestStack->getCurrentRequest() !== null) {
            $locale = $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale();
        }
        $subscriberEvent = new StockAlertEvent(
            $subscribeForm['product_sale_elements_id'],
            $subscribeForm['email'],
            $subscribeForm['newsletter'],
            $locale
        );

        $this->eventDispatcher->dispatch($subscriberEvent, StockAlertEvents::STOCK_ALERT_SUBSCRIBE);

        return Translator::getInstance()->trans(
            "Got it! You’ll receive an email as soon as the product is back in stock.",
            [],
            StockAlert::MESSAGE_DOMAIN
        );
    }
}
