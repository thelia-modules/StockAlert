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
use StockAlert\StockAlert;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\Sale\ProductSaleStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Product;
use Thelia\Model\Sale;

/**
 * Goes through the real core actions: the listener runs after them and reads
 * the prices they wrote.
 */
final class PriceDropAlertListenerTest extends PriceDropTestCase
{
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
    }

    public function testLoweringAPriceInTheBackOfficeQueuesTheSubscribers(): void
    {
        $product = $this->productPricedAt(40.0);
        $alert = $this->activeAlert($product->getDefaultSaleElements(), $this->uniqueEmail(), 40.0);

        $this->dispatcher->dispatch($this->backOfficePriceUpdate($product, 32.0), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_QUEUED, $alert->getStatus());
        self::assertSame(32.0, (float) $alert->getNewPrice());
    }

    public function testASmallPriceChangeInTheBackOfficeQueuesNobody(): void
    {
        $product = $this->productPricedAt(40.0);
        $alert = $this->activeAlert($product->getDefaultSaleElements(), $this->uniqueEmail(), 40.0);

        $this->dispatcher->dispatch($this->backOfficePriceUpdate($product, 39.90), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
    }

    public function testOpeningASaleOperationQueuesTheSubscribersOfItsProducts(): void
    {
        $product = $this->productPricedAt(40.0);
        $alert = $this->activeAlert($product->getDefaultSaleElements(), $this->uniqueEmail(), 40.0);
        $sale = $this->saleOf($product, active: true, percentOff: 20.0);

        $this->dispatcher->dispatch(new ProductSaleStatusUpdateEvent($sale), TheliaEvents::UPDATE_PRODUCT_SALE_STATUS);

        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_QUEUED, $alert->getStatus());
        self::assertEqualsWithDelta(32.0, (float) $alert->getNewPrice(), 0.01);
    }

    public function testClosingASaleOperationQueuesNobody(): void
    {
        $product = $this->productPricedAt(40.0);
        $alert = $this->activeAlert($product->getDefaultSaleElements(), $this->uniqueEmail(), 40.0);
        $sale = $this->saleOf($product, active: false, percentOff: 20.0);

        $this->dispatcher->dispatch(new ProductSaleStatusUpdateEvent($sale), TheliaEvents::UPDATE_PRODUCT_SALE_STATUS);

        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
    }

    public function testADisabledFeatureLeavesEveryPriceChangeAlone(): void
    {
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_ENABLED, '0');
        $product = $this->productPricedAt(40.0);
        $alert = $this->activeAlert($product->getDefaultSaleElements(), $this->uniqueEmail(), 40.0);

        $this->dispatcher->dispatch($this->backOfficePriceUpdate($product, 10.0), TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        $alert->reload();
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $alert->getStatus());
    }

    private function backOfficePriceUpdate(Product $product, float $price): ProductSaleElementUpdateEvent
    {
        $productSaleElements = $product->getDefaultSaleElements();

        return (new ProductSaleElementUpdateEvent($product, $productSaleElements->getId()))
            ->setReference((string) $productSaleElements->getRef())
            ->setPrice($price)
            ->setCurrencyId($this->currency->getId())
            ->setWeight(0.0)
            ->setQuantity((float) $productSaleElements->getQuantity())
            ->setSalePrice($price)
            ->setOnsale(0)
            ->setIsnew(0)
            ->setIsdefault(true)
            ->setEanCode('')
            ->setTaxRuleId((int) $product->getTaxRuleId())
            ->setFromDefaultCurrency(0);
    }

    private function saleOf(Product $product, bool $active, float $percentOff): Sale
    {
        $sale = $this->factory->sale(['active' => $active, 'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE]);
        $this->factory->saleOffsetCurrency($sale, $this->currency, $percentOff);
        $this->factory->saleProduct($sale, $product);

        return $sale;
    }
}
