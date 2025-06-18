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

    public function subscribe(array $subscribeForm) : string
    {
        $subscriberEvent = new StockAlertEvent(
            $subscribeForm['product_sale_elements_id'],
            $subscribeForm['email'],
            $subscribeForm['newsletter'],
            $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale()
        );

        $this->eventDispatcher->dispatch($subscriberEvent, StockAlertEvents::STOCK_ALERT_SUBSCRIBE);

        return Translator::getInstance()->trans(
            "C’est noté ! Vous recevrez un e-mail dès que le produit sera de nouveau en stock.",
            [],
            StockAlert::MESSAGE_DOMAIN
        );
    }
}
