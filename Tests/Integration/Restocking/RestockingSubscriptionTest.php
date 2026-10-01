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

namespace StockAlert\Tests\Integration\Restocking;

use StockAlert\EventListeners\StockAlertManager;
use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\Exception\SubscriptionRefusedException;
use StockAlert\Form\StockAlertSubscribe;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\Service\RestockingMailer;
use StockAlert\Service\StockAlertService;
use StockAlert\StockAlert;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Form\FormServiceInterface;
use Thelia\Model\NewsletterQuery;
use Thelia\Tools\URL;

/**
 * The subscription and the product coming back, through the real dispatcher: the module's listener keeps its
 * table, in the language of the subscriber, and tells them in it.
 */
final class RestockingSubscriptionTest extends RestockingTestCase
{
    private EventDispatcherInterface $dispatcher;

    private StockAlertManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->manager = new StockAlertManager($this->mailer, new EventDispatcher(), new RestockingMailer($this->mailer, $this->getService(URL::class)));
    }

    public function testASubscriptionIsKeptWithTheLanguageOfTheSubscriber(): void
    {
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $email, false, 'de_DE'));

        $alert = RestockingAlertQuery::create()->filterByEmail($email)->findOne();
        self::assertNotNull($alert);
        self::assertSame('de_DE', $alert->getLocale());
        self::assertSame([], $this->mailer->sent, 'no acknowledgement unless the shop asks for it');
    }

    public function testTheSameAddressIsRecordedOnce(): void
    {
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $email, false, 'fr_FR'));
        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $email, false, 'fr_FR'));

        self::assertSame(1, $this->subscriptionsOf($productSaleElements->getId()));
    }

    public function testTheAcknowledgementIsSentOnceInTheLanguageOfTheSubscriberWhenTheShopAsksForIt(): void
    {
        $this->writeConfig(StockAlert::CONFIG_CONFIRMATION, '1');
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $email, false, 'de_DE'));
        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $email, false, 'de_DE'));

        self::assertCount(1, $this->mailer->sent, 'not again for an address already recorded');
        self::assertSame(StockAlert::MESSAGE_SUBSCRIBED, $this->mailer->sent[0]['code']);
        self::assertSame('de_DE', $this->mailer->sent[0]['locale']);
    }

    public function testAnAcknowledgementThatDoesNotLeaveKeepsTheSubscription(): void
    {
        $this->writeConfig(StockAlert::CONFIG_CONFIRMATION, '1');
        $this->mailer->failing = true;
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();

        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $this->uniqueEmail(), false, 'fr_FR'));

        self::assertSame(1, $this->subscriptionsOf($productSaleElements->getId()));
    }

    public function testTheNewsletterIsSubscribedInTheLanguageOfTheStorefrontNotInFrench(): void
    {
        $subscribed = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TheliaEvents::NEWSLETTER_SUBSCRIBE, static function (NewsletterEvent $event) use (&$subscribed): void {
            $subscribed[] = [$event->getEmail(), $event->getLocale()];
        });
        $manager = new StockAlertManager($this->mailer, $dispatcher, new RestockingMailer($this->mailer, $this->getService(URL::class)));
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $email, true, 'de_DE'));

        self::assertSame([[$email, 'de_DE']], $subscribed);
        self::assertNull(NewsletterQuery::create()->findOneByEmail($email), 'the newsletter listener of the shop does that, not this test');
    }

    public function testTheProductBackInStockIsToldToTheSubscribersInTheirLanguageThenForgotten(): void
    {
        $productSaleElements = $this->productOutOfStock('Casque jet')->getDefaultSaleElements();
        $german = $this->uniqueEmail('hans');
        $french = $this->uniqueEmail('jean');
        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $german, false, 'de_DE'));
        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $french, false, 'fr_FR'));
        $productSaleElements->setQuantity(5)->save();

        $this->manager->checkStock($this->restockEvent($productSaleElements->getId(), 5.0));

        self::assertSame(['de_DE', 'fr_FR'], array_column($this->mailer->sent, 'locale'));
        self::assertSame(StockAlert::MESSAGE_CUSTOMER, $this->mailer->sent[0]['code']);
        self::assertSame($german, array_key_first($this->mailer->sent[0]['to']));
        self::assertSame('Casque jet', $this->mailer->sent[0]['parameters']['product_title']);
        self::assertSame(0, $this->subscriptionsOf($productSaleElements->getId()));
    }

    public function testASubscriberWhoseMessageDidNotLeaveIsKeptForTheNextTime(): void
    {
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $this->manager->subscribe(new StockAlertEvent($productSaleElements->getId(), $this->uniqueEmail(), false, 'fr_FR'));
        $productSaleElements->setQuantity(5)->save();
        $this->mailer->failing = true;

        $this->manager->checkStock($this->restockEvent($productSaleElements->getId(), 5.0));

        self::assertSame(1, $this->subscriptionsOf($productSaleElements->getId()));
    }

    public function testAListenerCanRefuseASubscriptionAndTheModuleNeverRecordsIt(): void
    {
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $refuse = static function (): never {
            throw new SubscriptionRefusedException('Too many requests.');
        };
        $this->dispatcher->addListener(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $refuse, 256);

        try {
            $this->getService(StockAlertService::class)->subscribe(['product_sale_elements_id' => $productSaleElements->getId(), 'email' => $this->uniqueEmail()]);
            self::fail('The subscription was not refused');
        } catch (SubscriptionRefusedException $refused) {
            self::assertSame('Too many requests.', $refused->getMessage());
        } finally {
            $this->dispatcher->removeListener(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $refuse);
        }

        self::assertSame(0, $this->subscriptionsOf($productSaleElements->getId()));
    }

    public function testTheNewsletterBoxIsNotOfferedUnlessTheShopAsksForIt(): void
    {
        $formService = $this->getService(FormServiceInterface::class);

        $without = $formService->getFormByName(StockAlertSubscribe::getName());
        self::assertFalse($without->has('newsletter'));
        self::assertTrue($without->has('email'));

        $this->writeConfig(StockAlert::CONFIG_NEWSLETTER, '1');
        $with = $formService->getFormByName(StockAlertSubscribe::getName());
        self::assertTrue($with->has('newsletter'));
    }

    public function testTheTickOfANewsletterBoxTheShopDoesNotOfferIsIgnored(): void
    {
        $events = [];
        $listener = static function (StockAlertEvent $event) use (&$events): void {
            $events[] = $event->getSubscribeToNewsLetter();
            $event->stopPropagation();
        };
        $this->dispatcher->addListener(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $listener, 256);
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $service = $this->getService(StockAlertService::class);

        $service->subscribe(['product_sale_elements_id' => $productSaleElements->getId(), 'email' => $this->uniqueEmail(), 'newsletter' => true]);
        $this->writeConfig(StockAlert::CONFIG_NEWSLETTER, '1');
        $service->subscribe(['product_sale_elements_id' => $productSaleElements->getId(), 'email' => $this->uniqueEmail(), 'newsletter' => true]);
        $this->dispatcher->removeListener(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $listener);

        self::assertSame([false, true], $events);
    }

    private function restockEvent(int $productSaleElementsId, float $quantity): ProductSaleElementUpdateEvent
    {
        $event = $this->createStub(ProductSaleElementUpdateEvent::class);
        $event->method('getProductSaleElementId')->willReturn($productSaleElementsId);
        $event->method('getQuantity')->willReturn($quantity);

        return $event;
    }
}
