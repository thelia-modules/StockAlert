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
use StockAlert\PriceDrop\PriceDropDetector;

final class PriceDropDetectorTest extends PriceDropTestCase
{
    private PriceDropDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = $this->getService(PriceDropDetector::class);
    }

    public function testADropAboveTheThresholdIsQueuedWithTheNewPrice(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0);
        $this->repriceDefaultCurrency($productSaleElements, 32.0);

        self::assertSame(1, $this->detector->check([$productSaleElements->getId()]));

        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_QUEUED, $alert->getStatus());
        self::assertSame(32.0, (float) $alert->getNewPrice());
        self::assertNotNull($alert->getQueuedAt());
    }

    public function testAPromotionOnTheSaleElementIsADrop(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0);
        $this->repriceDefaultCurrency($productSaleElements, 40.0, 30.0, promo: true);

        self::assertSame(1, $this->detector->check([$productSaleElements->getId()]));
        $alert->reload();
        self::assertSame(30.0, (float) $alert->getNewPrice());
    }

    public function testADropUnderTheThresholdLeavesTheSubscriptionWaiting(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0);
        $this->repriceDefaultCurrency($productSaleElements, 39.90);

        self::assertSame(0, $this->detector->check([$productSaleElements->getId()]));
        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
        self::assertNull($alert->getNewPrice());
    }

    public function testARaiseFollowedByADropBackToTheReferenceIsNotADrop(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0);

        $this->repriceDefaultCurrency($productSaleElements, 50.0);
        self::assertSame(0, $this->detector->check([$productSaleElements->getId()]));
        $this->repriceDefaultCurrency($productSaleElements, 40.0);
        self::assertSame(0, $this->detector->check([$productSaleElements->getId()]));

        $alert->reload();

        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
    }

    public function testAnExpiredSubscriptionIsIgnored(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0, ['expiresAt' => new \DateTimeImmutable('-1 minute')]);
        $this->repriceDefaultCurrency($productSaleElements, 20.0);

        self::assertSame(0, $this->detector->check([$productSaleElements->getId()]));
        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
    }

    public function testAnAlreadyQueuedSubscriptionIsNotQueuedAgain(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0, ['status' => PriceDropAlert::STATUS_QUEUED]);
        $this->repriceDefaultCurrency($productSaleElements, 20.0);

        self::assertSame(0, $this->detector->check([$productSaleElements->getId()]));
        $alert->reload();
        self::assertNull($alert->getNewPrice());
    }

    public function testOnlyTheSaleElementsAskedForAreLookedAt(): void
    {
        $followed = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $other = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $this->activeAlert($followed, $this->uniqueEmail(), 40.0);
        $otherAlert = $this->activeAlert($other, $this->uniqueEmail(), 40.0);
        $this->repriceDefaultCurrency($followed, 20.0);
        $this->repriceDefaultCurrency($other, 20.0);

        self::assertSame(1, $this->detector->check([$followed->getId()]));
        $otherAlert->reload();
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $otherAlert->getStatus());
    }

    public function testCheckAllSweepsEverySubscription(): void
    {
        $first = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $second = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $this->activeAlert($first, $this->uniqueEmail(), 40.0);
        $this->activeAlert($second, $this->uniqueEmail(), 40.0);
        $this->repriceDefaultCurrency($first, 20.0);
        $this->repriceDefaultCurrency($second, 20.0);

        self::assertSame(2, $this->detector->checkAll());
        self::assertSame(0, $this->detector->checkAll());
    }

    public function testASubscriptionInAnotherCurrencyIsComparedInThatCurrency(): void
    {
        $doubled = $this->factory->currency(['rate' => 2.0 * $this->currency->getRate()]);
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        // The visitor saw 80 in that currency, converted from the default one.
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 80.0, ['currencyId' => $doubled->getId()]);

        $this->repriceDefaultCurrency($productSaleElements, 30.0);

        self::assertSame(1, $this->detector->check([$productSaleElements->getId()]));
        $alert->reload();
        self::assertSame(60.0, (float) $alert->getNewPrice());
    }

    public function testASubscriptionOnADeletedSaleElementDisappearsWithIt(): void
    {
        $product = $this->productPricedAt(40.0);
        $productSaleElements = $product->getDefaultSaleElements();
        $alert = $this->activeAlert($productSaleElements, $this->uniqueEmail(), 40.0);

        $productSaleElements->delete();

        self::assertNull(PriceDropAlertQuery::create()->findPk($alert->getId()));
        self::assertSame(0, $this->detector->check([$productSaleElements->getId()]));
    }
}
