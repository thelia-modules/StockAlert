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
use StockAlert\PriceDrop\PriceDropSubscriptionService;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Shared ground for the price drop tests: the feature switched on with a known
 * threshold, a caller address of its own, and the configuration put back after.
 */
abstract class PriceDropTestCase extends IntegrationTestCase
{
    protected FixtureFactory $factory;
    protected Currency $currency;

    /** @var array<string, ?string> */
    private array $previousConfig = [];
    private ?string $previousClientIp = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();

        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_ENABLED, '1');
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT, '5');
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL, '20');

        $request = $this->getService(RequestStack::class)->getMainRequest();
        $this->previousClientIp = $request?->server->get('REMOTE_ADDR');
        $request?->server->set('REMOTE_ADDR', '203.0.113.'.random_int(1, 254));
    }

    protected function tearDown(): void
    {
        $this->getService(RequestStack::class)->getMainRequest()?->server->set('REMOTE_ADDR', $this->previousClientIp);

        // The transaction rolls the rows back, not the configuration cache.
        foreach ($this->previousConfig as $name => $value) {
            if (null === $value) {
                ConfigQuery::create()->filterByName($name)->delete();
                continue;
            }
            ConfigQuery::write($name, $value);
        }

        parent::tearDown();
    }

    protected function writeConfig(string $name, string $value): void
    {
        if (!\array_key_exists($name, $this->previousConfig)) {
            $this->previousConfig[$name] = ConfigQuery::read($name);
        }

        ConfigQuery::write($name, $value);
    }

    protected function productPricedAt(float $untaxedPrice): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            ['basePrice' => $untaxedPrice, 'baseQuantity' => 10, 'title' => 'Followed product'],
        );
    }

    protected function repriceDefaultCurrency(ProductSaleElements $productSaleElements, float $price, ?float $promoPrice = null, bool $promo = false): void
    {
        ProductPriceQuery::create()
            ->filterByProductSaleElementsId($productSaleElements->getId())
            ->filterByCurrencyId($this->currency->getId())
            ->update([
                'Price' => PriceDropSubscriptionService::decimal($price),
                'PromoPrice' => PriceDropSubscriptionService::decimal($promoPrice ?? $price),
            ]);

        $productSaleElements->setPromo($promo ? 1 : 0)->save();
    }

    protected function activeAlert(ProductSaleElements $productSaleElements, string $email, float $referencePrice, array $overrides = []): PriceDropAlert
    {
        $alert = (new PriceDropAlert())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setEmail($email)
            ->setLocale('en_US')
            ->setCurrencyId($overrides['currencyId'] ?? $this->currency->getId())
            ->setCustomerId($overrides['customerId'] ?? null)
            ->setReferencePrice(PriceDropSubscriptionService::decimal($referencePrice))
            ->setStatus($overrides['status'] ?? PriceDropAlert::STATUS_ACTIVE)
            ->setExpiresAt($overrides['expiresAt'] ?? new \DateTimeImmutable('+30 days'));
        $alert->save();

        return $alert;
    }

    protected function uniqueEmail(string $prefix = 'shopper'): string
    {
        return \sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(6)));
    }
}
