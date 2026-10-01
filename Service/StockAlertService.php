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

    /**
     * @param array{product_sale_elements_id: int|string|null, email: string, newsletter?: bool|null} $subscribeForm
     *
     * @throws \StockAlert\Exception\SubscriptionRefusedException when a listener refuses the subscription: its
     *                                                           message is for the visitor
     */
    public function subscribe(array $subscribeForm): string
    {
        $locale = \Thelia\Model\LangQuery::create()->findOneByByDefault(true)?->getLocale() ?? 'en_US';
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && $request->hasSession()) {
            $locale = $request->getSession()->getLang()->getLocale();
        }
        $subscriberEvent = new StockAlertEvent(
            $subscribeForm['product_sale_elements_id'],
            $subscribeForm['email'],
            // The checkbox only exists when the shop offers the newsletter (setting stockalert_newsletter).
            StockAlert::getConfig()['newsletter'] && !empty($subscribeForm['newsletter']),
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
