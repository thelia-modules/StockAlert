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

namespace StockAlert\Tests\Http;

use StockAlert\StockAlert;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The product page of the installed front theme, with the module's alerts block.
 * Skipped when the theme does not call the alerts hook yet: the block is the
 * module's, the hook call is the theme's, and the two ship separately.
 */
final class FrontProductAlertsTest extends WebIntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var array<string, ?string> */
    private array $previousConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $selector = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate()->getAbsolutePath()
            .'/components/Organisms/PseSelector/Base.html.twig';

        if (!is_file($selector) || !str_contains((string) file_get_contents($selector), 'product.pse.alerts')) {
            self::markTestSkipped('The installed front theme does not call the product.pse.alerts hook.');
        }

        // Not createFixtureFactory(): that helper pushes a synthetic request on the stack,
        // and the page would then render against a request without a session.
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_ENABLED, '1');
        // The out-of-stock branch of the theme only exists when stock is enforced.
        $this->writeConfig('check-available-stock', '1');
    }

    protected function tearDown(): void
    {
        foreach ($this->previousConfig as $name => $value) {
            if (null === $value) {
                ConfigQuery::create()->filterByName($name)->delete();
                continue;
            }
            ConfigQuery::write($name, $value);
        }

        parent::tearDown();
    }

    public function testTheBlockOffersThePriceAlertOnAnInStockVariant(): void
    {
        $product = $this->product(quantity: 10);

        $this->assertPageRenders('/'.$this->urlOf($product));
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('class="ProductAlerts', $html);
        self::assertStringContainsString('data-live-name-value="PriceDropAlert"', $html);
        self::assertStringNotContainsString('data-live-name-value="StockAlert"', $html, 'in stock: no restocking form');
        self::assertStringContainsString('stockalert_price_drop_subscribe_form[email]', $html);
    }

    public function testBothAlertsShareTheBlockOnAnOutOfStockVariant(): void
    {
        $product = $this->product(quantity: 0);

        $this->assertPageRenders('/'.$this->urlOf($product));
        $html = (string) $this->client->getResponse()->getContent();

        self::assertSame(1, substr_count($html, 'class="ProductAlerts'), 'one block, not two');
        self::assertStringContainsString('data-live-name-value="StockAlert"', $html);
        self::assertStringContainsString('data-live-name-value="PriceDropAlert"', $html);
    }

    public function testADisabledFeatureLeavesThePageAsBefore(): void
    {
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_ENABLED, '0');
        $product = $this->product(quantity: 10);

        $this->assertPageRenders('/'.$this->urlOf($product));
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('ProductAlerts', $html);
        self::assertStringNotContainsString('PriceDropAlert', $html);
    }

    public function testTheRestockingFormStillShowsAloneWhenTheFeatureIsOff(): void
    {
        $this->writeConfig(StockAlert::CONFIG_PRICE_DROP_ENABLED, '0');
        $product = $this->product(quantity: 0);

        $this->assertPageRenders('/'.$this->urlOf($product));
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('data-live-name-value="StockAlert"', $html);
        self::assertStringNotContainsString('PriceDropAlert', $html);
    }

    private function product(int $quantity): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => 40.0, 'baseQuantity' => $quantity, 'title' => 'Followed product', 'locale' => 'en_US'],
        );
    }

    private function urlOf(Product $product): string
    {
        $url = 'followed-product-'.$product->getId().'.html';
        $product->setRewrittenUrl('en_US', $url);

        return $url;
    }

    /**
     * Written with a plain query, not ConfigQuery::write(): the model event the latter
     * dispatches before the first request leaves the page rendering without a session.
     */
    private function writeConfig(string $name, string $value): void
    {
        if (!\array_key_exists($name, $this->previousConfig)) {
            $this->previousConfig[$name] = ConfigQuery::read($name);
        }

        $config = ConfigQuery::create()->findOneByName($name);

        if (null === $config) {
            ConfigQuery::write($name, $value);

            return;
        }

        ConfigQuery::create()->filterByName($name)->update(['Value' => $value]);
        ConfigQuery::resetCache();
    }
}
