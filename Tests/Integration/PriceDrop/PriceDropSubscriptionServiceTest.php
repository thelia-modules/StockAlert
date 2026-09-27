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

namespace StockAlert\Tests\Integration\PriceDrop;

use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\PriceDrop\PriceDropSubscriptionService;
use StockAlert\PriceDrop\SubscriptionRefusal;
use StockAlert\PriceDrop\SubscriptionRefusedException;
use StockAlert\PriceDrop\SubscriptionRequest;
use StockAlert\StockAlert;
use Thelia\Model\CustomerTitleQuery;

final class PriceDropSubscriptionServiceTest extends PriceDropTestCase
{
    private PriceDropSubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(PriceDropSubscriptionService::class);
    }

    public function testASubscriptionRecordsTheUntaxedPriceTheVisitorSaw(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();

        $suffix = bin2hex(random_bytes(4));
        $outcome = $this->service->subscribe($this->request($productSaleElements->getId(), 'Shopper-'.$suffix.'@Example.com '));

        self::assertSame(40.0, $outcome->untaxedReferencePrice);

        $alert = PriceDropAlertQuery::create()->filterByProductSaleElementsId($productSaleElements->getId())->findOne();
        self::assertNotNull($alert);
        self::assertSame($alert->getId(), $outcome->priceDropAlertId);
        self::assertSame($alert->getExpiresAt()->getTimestamp(), $outcome->expiresAt->getTimestamp());
        self::assertSame('shopper-'.$suffix.'@example.com', $alert->getEmail());
        self::assertSame(40.0, (float) $alert->getReferencePrice());
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
        self::assertSame($this->currency->getId(), $alert->getCurrencyId());
        self::assertSame('fr_FR', $alert->getLocale());
        self::assertNull($alert->getCustomerId());
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+180 days'))->getTimestamp(),
            $alert->getExpiresAt()->getTimestamp(),
            120,
        );
    }

    public function testThePromoPriceIsTheReferenceWhenTheSaleElementIsOnPromotion(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $this->repriceDefaultCurrency($productSaleElements, 40.0, 32.0, promo: true);

        $outcome = $this->service->subscribe($this->request($productSaleElements->getId(), $this->uniqueEmail()));

        self::assertSame(32.0, $outcome->untaxedReferencePrice);
    }

    public function testAKnownCustomerIsAttachedToItsSubscription(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $customer = $this->factory->customer(CustomerTitleQuery::create()->findOne(), ['email' => $this->uniqueEmail('customer')]);

        $this->service->subscribe(new SubscriptionRequest(
            $productSaleElements->getId(),
            $customer->getEmail(),
            'en_US',
            $this->currency->getId(),
            $customer->getId(),
        ));

        self::assertSame($customer->getId(), PriceDropAlertQuery::create()->findOneByEmail($customer->getEmail())?->getCustomerId());
    }

    public function testSubscribingTwiceKeepsTheFirstReferenceAndOneRow(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $first = $this->service->subscribe($this->request($productSaleElements->getId(), $email));
        $this->repriceDefaultCurrency($productSaleElements, 50.0);
        $second = $this->service->subscribe($this->request($productSaleElements->getId(), $email));

        self::assertSame(40.0, $second->untaxedReferencePrice);
        self::assertSame($first->priceDropAlertId, $second->priceDropAlertId);
        self::assertSame(1, PriceDropAlertQuery::create()->filterByEmail($email)->count());
    }

    public function testSubscribingAgainAfterExpiryRevivesTheRowFromTheCurrentPrice(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $email = $this->uniqueEmail('back');
        $expired = $this->activeAlert($productSaleElements, $email, 50.0, ['expiresAt' => new \DateTimeImmutable('-1 day')]);

        $outcome = $this->service->subscribe($this->request($productSaleElements->getId(), $email));

        self::assertSame($expired->getId(), $outcome->priceDropAlertId);
        self::assertTrue($outcome->ownedByCaller);
        self::assertSame(40.0, $outcome->untaxedReferencePrice, 'the revived subscription compares against the price the visitor sees now');
        self::assertGreaterThan(new \DateTimeImmutable(), $outcome->expiresAt);
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, PriceDropAlertQuery::create()->findPk($expired->getId())?->getStatus());
        self::assertSame(1, PriceDropAlertQuery::create()->filterByEmail($email)->count());
    }

    public function testOnlyTheFirstSubscriberOfAnAddressOwnsTheSubscription(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $first = $this->service->subscribe($this->request($productSaleElements->getId(), $email));
        $second = $this->service->subscribe($this->request($productSaleElements->getId(), $email));

        self::assertTrue($first->ownedByCaller);
        self::assertFalse($second->ownedByCaller, 'whoever types a known address again must not get a link that deletes the first subscription');
    }

    public function testACustomerOwnsTheSubscriptionAttachedToItsAccount(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $customer = $this->factory->customer(CustomerTitleQuery::create()->findOne(), ['email' => $this->uniqueEmail('customer')]);
        $request = new SubscriptionRequest($productSaleElements->getId(), $customer->getEmail(), 'fr_FR', $this->currency->getId(), $customer->getId());

        $this->service->subscribe($request);
        $again = $this->service->subscribe($request);
        $guest = $this->service->subscribe($this->request($productSaleElements->getId(), $customer->getEmail()));

        self::assertTrue($again->ownedByCaller);
        self::assertFalse($guest->ownedByCaller);
    }

    public function testADisabledFeatureRefusesEverySubscription(): void
    {
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_ENABLED, '0');
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();

        $this->expectRefusal(SubscriptionRefusal::Disabled);
        $this->service->subscribe($this->request($productSaleElements->getId(), $this->uniqueEmail()));
    }

    public function testAnUnknownSaleElementIsRefused(): void
    {
        $this->expectRefusal(SubscriptionRefusal::UnknownProductSaleElement);
        $this->service->subscribe($this->request(999999999, $this->uniqueEmail()));
    }

    public function testAnAddressOverItsQuotaIsRefused(): void
    {
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL, '2');
        $email = $this->uniqueEmail('greedy');
        $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $email, 10.0);
        $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $email, 10.0);
        $third = $this->productPricedAt(10.0)->getDefaultSaleElements();

        $this->expectRefusal(SubscriptionRefusal::QuotaExceeded);
        $this->service->subscribe($this->request($third->getId(), $email));
    }

    public function testExpiredAndQueuedSubscriptionsDoNotCountTowardsTheQuota(): void
    {
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL, '1');
        $email = $this->uniqueEmail('patient');
        $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $email, 10.0, ['expiresAt' => new \DateTimeImmutable('-1 day')]);
        $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $email, 10.0, ['status' => PriceDropAlert::STATUS_QUEUED]);

        $this->service->subscribe($this->request($this->productPricedAt(10.0)->getDefaultSaleElements()->getId(), $email));

        self::assertSame(3, PriceDropAlertQuery::create()->filterByEmail($email)->count());
    }

    public function testTheSameAddressCannotAskMoreThanFiveTimesAnHour(): void
    {
        $email = $this->uniqueEmail('hammer');

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->service->subscribe($this->request($this->productPricedAt(10.0)->getDefaultSaleElements()->getId(), $email));
        }

        $this->expectRefusal(SubscriptionRefusal::RateLimited);
        $this->service->subscribe($this->request($this->productPricedAt(10.0)->getDefaultSaleElements()->getId(), $email));
    }

    public function testUnsubscribingRemovesTheRowAndSaysNothingTwice(): void
    {
        $alert = $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $this->uniqueEmail(), 10.0);

        $this->service->unsubscribe($alert->getId());
        $this->service->unsubscribe($alert->getId());

        self::assertNull(PriceDropAlertQuery::create()->findPk($alert->getId()));
    }

    private function request(int $productSaleElementsId, string $email): SubscriptionRequest
    {
        return new SubscriptionRequest($productSaleElementsId, $email, 'fr_FR', $this->currency->getId());
    }

    private function expectRefusal(SubscriptionRefusal $refusal): void
    {
        $this->expectException(SubscriptionRefusedException::class);
        $this->expectExceptionMessage($refusal->value);
    }
}
