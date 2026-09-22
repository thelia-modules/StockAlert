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

namespace StockAlert\Tests\Integration;

use Propel\Runtime\Exception\PropelException;
use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\IntegrationTestCase;

/**
 * The price drop alert table is what every later milestone writes into, so what
 * it accepts and what it refuses is worth pinning down before any service reads
 * it: a subscription survives a round trip with its own values, and the same
 * address cannot subscribe twice to the same sale element.
 */
final class PriceDropAlertSchemaTest extends IntegrationTestCase
{
    public function testAnAlertIsReadBackWithTheValuesItWasSavedWith(): void
    {
        [$productSaleElements, $currency] = $this->createSaleElement();

        $expiresAt = new \DateTime('+180 days');

        $alert = new PriceDropAlert();
        $alert
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setEmail('price-drop@example.com')
            ->setLocale('en_US')
            ->setCurrencyId($currency->getId())
            ->setReferencePrice('40.000000')
            ->setStatus('active')
            ->setExpiresAt($expiresAt)
            ->save($this->getPropelConnection());

        $reloaded = PriceDropAlertQuery::create()->findPk($alert->getId(), $this->getPropelConnection());

        self::assertInstanceOf(PriceDropAlert::class, $reloaded);
        self::assertSame($productSaleElements->getId(), $reloaded->getProductSaleElementsId());
        self::assertNull($reloaded->getCustomerId());
        self::assertSame('price-drop@example.com', $reloaded->getEmail());
        self::assertSame('en_US', $reloaded->getLocale());
        self::assertSame($currency->getId(), $reloaded->getCurrencyId());
        // Propel hands DECIMAL columns back as strings, so the comparison is
        // made on a cast rather than on the stored representation.
        self::assertSame(40.0, (float) $reloaded->getReferencePrice());
        self::assertNull($reloaded->getNewPrice());
        self::assertSame('active', $reloaded->getStatus());
        self::assertSame(0, $reloaded->getAttempts());
        self::assertNull($reloaded->getQueuedAt());
        self::assertSame($expiresAt->format('Y-m-d H:i:s'), $reloaded->getExpiresAt('Y-m-d H:i:s'));
        self::assertNotNull($reloaded->getCreatedAt());
    }

    public function testTheSameAddressCannotSubscribeTwiceToTheSameSaleElement(): void
    {
        [$productSaleElements, $currency] = $this->createSaleElement();

        $this->createAlert($productSaleElements, $currency, 'duplicate@example.com');

        $this->expectException(PropelException::class);

        $this->createAlert($productSaleElements, $currency, 'duplicate@example.com');
    }

    /**
     * @return array{ProductSaleElements, Currency}
     */
    private function createSaleElement(): array
    {
        $factory = $this->createFixtureFactory();

        $currency = $factory->currency();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $currency,
            ['title' => 'Price drop product', 'basePrice' => 50.0],
        );

        $productSaleElements = $factory->productSaleElement($product);

        return [$productSaleElements, $currency];
    }

    private function createAlert(ProductSaleElements $productSaleElements, Currency $currency, string $email): void
    {
        (new PriceDropAlert())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setEmail($email)
            ->setLocale('en_US')
            ->setCurrencyId($currency->getId())
            ->setReferencePrice('40.000000')
            ->setStatus('active')
            ->setExpiresAt(new \DateTime('+180 days'))
            ->save($this->getPropelConnection());
    }
}
