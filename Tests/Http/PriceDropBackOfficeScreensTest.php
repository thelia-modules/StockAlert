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

use StockAlert\Model\PriceDropAlert;
use StockAlert\StockAlert;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The two back-office screens of the price drop alert: the settings card on the
 * module configuration page, and the follow-up list of the products people are
 * waiting a drop on.
 */
final class PriceDropBackOfficeScreensTest extends WebIntegrationTestCase
{
    private const CONFIGURATION_URL = '/admin/module/StockAlert';
    private const LIST_URL = '/admin/modules/StockAlert/price-drop';
    private const SAVE_URL = '/admin/modules/StockAlert/price-drop/save';
    private const LEGACY_SAVE_URL = '/admin/modules/StockAlert/save';

    private const CONFIGURATION_KEYS = [
        StockAlert::CONFIG_PRICE_DROP_ENABLED,
        StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT,
        StockAlert::CONFIG_PRICE_DROP_EXPIRATION_DAYS,
        StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL,
        StockAlert::CONFIG_PRICE_DROP_BATCH_SIZE,
    ];

    private ?AdminSessionInjector $injector = null;

    /** @var array<string, ?string> */
    private array $previousConfiguration = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        foreach (self::CONFIGURATION_KEYS as $key) {
            $this->previousConfiguration[$key] = ConfigQuery::read($key);
        }
    }

    protected function tearDown(): void
    {
        // The transaction rolls the rows back, not the configuration cache the
        // whole process reads from, so the values are put back by hand.
        foreach ($this->previousConfiguration as $key => $value) {
            if (null === $value) {
                ConfigQuery::create()->filterByName($key)->delete();
                continue;
            }

            ConfigQuery::write($key, $value);
        }

        $this->injector?->clear();

        parent::tearDown();
    }

    public function testTheModuleConfigurationPageCarriesThePriceDropCard(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::CONFIGURATION_URL);

        $crawler = $this->client->getCrawler();

        self::assertSame(
            1,
            $crawler->filter('[data-testid="stockalert-price-drop-card"]')->count(),
            'The configuration page carries the price drop card.',
        );
        self::assertSame(
            1,
            $crawler->filter('#stockalert-price-drop-save')->count(),
            'The card carries its own save button, next to the stock alert one.',
        );
        self::assertStringContainsString(
            self::LIST_URL,
            (string) $crawler->filter('[data-testid="stockalert-price-drop-link"]')->attr('href'),
            'The card links to the follow-up screen.',
        );
    }

    public function testSavingTheCardWritesTheFiveConfigurationKeys(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::CONFIGURATION_URL);

        // Submitting the rendered form and not a hand-built POST: the form
        // carries a CSRF token, and a POST without it is refused before any
        // value is written.
        $form = $this->client->getCrawler()->filter('#stockalert-price-drop-save')->form();
        $form['stockalert_price_drop_config_form[enabled]']->tick();

        $this->client->submit($form, [
            'stockalert_price_drop_config_form[threshold_percent]' => '12.5',
            'stockalert_price_drop_config_form[expiration_days]' => '45',
            'stockalert_price_drop_config_form[max_per_email]' => '7',
            'stockalert_price_drop_config_form[batch_size]' => '123',
        ]);

        self::assertTrue(
            $this->client->getResponse()->isRedirect(),
            'A saved form sends the administrator back to the module page.',
        );

        self::assertSame('1', ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_ENABLED));
        self::assertSame('12.5', ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT));
        self::assertSame('45', ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_EXPIRATION_DAYS));
        self::assertSame('7', ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL));
        self::assertSame('123', ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_BATCH_SIZE));
    }

    public function testTheUncheckedBoxSwitchesTheFeatureOff(): void
    {
        ConfigQuery::write(StockAlert::CONFIG_PRICE_DROP_ENABLED, '1');

        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders(self::CONFIGURATION_URL);

        $form = $this->client->getCrawler()->filter('#stockalert-price-drop-save')->form();
        $form['stockalert_price_drop_config_form[enabled]']->untick();

        $this->client->submit($form);

        self::assertSame('0', ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_ENABLED));
    }

    public function testTheFollowUpScreenCountsTheSubscriptionsOfAProduct(): void
    {
        $factory = $this->factory();
        $product = $this->followedProduct($factory, 'Followed on screen');
        $saleElements = $this->defaultSaleElements($product);

        $this->subscription($factory, $saleElements, 'first-subscriber@example.com');
        $this->subscription($factory, $saleElements, 'second-subscriber@example.com', [
            'status' => PriceDropAlert::STATUS_QUEUED,
        ]);
        $this->subscription($factory, $saleElements, 'expired-subscriber@example.com', [
            'expiresAt' => new \DateTimeImmutable('-1 day'),
        ]);

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::LIST_URL);

        $row = $this->rowNamed('Followed on screen');

        self::assertSame(1, $row->count(), 'The followed product has one row of its own.');

        $cells = $row->filter('td')->each(static fn (Crawler $cell): string => trim($cell->text()));

        self::assertContains(
            $product->getRef(),
            $cells,
            'The row carries the reference of the product.',
        );
        self::assertSame(
            ['2', '1'],
            \array_slice($cells, -2),
            'Two subscriptions are still pending, one of them already queued; the expired one is not counted.',
        );
    }

    public function testTheFollowUpScreenNeverRendersAnEmailAddress(): void
    {
        $factory = $this->factory();
        $product = $this->followedProduct($factory, 'Followed anonymously');
        $saleElements = $this->defaultSaleElements($product);

        $this->subscription($factory, $saleElements, 'never-rendered@example.com');

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::LIST_URL);

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Followed anonymously', $html, 'The product itself is listed.');
        self::assertStringNotContainsString('never-rendered@example.com', $html);
        self::assertStringNotContainsString('never-rendered', $html, 'Not even the local part of the address.');
    }

    public function testAnAdminWithoutTheModuleResourceCannotReachTheFollowUpScreen(): void
    {
        // The customer resource is granted on purpose: a profile holding nothing
        // at all is refused whichever resource the screen checks, which would
        // prove nothing about the module resource.
        $this->loginAs($this->factory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW],
        ]));

        $this->client->request('GET', self::LIST_URL);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnAdminWithoutTheModuleResourceCannotSaveTheStockAlertConfiguration(): void
    {
        $previousThreshold = ConfigQuery::read(StockAlert::CONFIG_THRESHOLD);

        $this->loginAs($this->factory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW],
        ]));

        $this->client->request('POST', self::LEGACY_SAVE_URL, [
            'stockalert_config_form' => [
                'enabled' => '1',
                'threshold' => '999',
                'emails' => 'intruder@example.com',
                'notify' => '1',
            ],
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            $previousThreshold,
            ConfigQuery::read(StockAlert::CONFIG_THRESHOLD),
            'The refused request writes nothing.',
        );
    }

    public function testAnAdminWithoutTheModuleResourceCannotSaveThePriceDropConfiguration(): void
    {
        $this->loginAs($this->factory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW],
        ]));

        $this->client->request('POST', self::SAVE_URL, [
            'stockalert_price_drop_config_form' => [
                'enabled' => '1',
                'threshold_percent' => '99',
                'expiration_days' => '1',
                'max_per_email' => '1',
                'batch_size' => '1',
            ],
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            $this->previousConfiguration[StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT],
            ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT),
            'The refused request writes nothing.',
        );
    }

    private function rowNamed(string $title): Crawler
    {
        return $this->client->getCrawler()->filter('table tbody tr')->reduce(
            static fn (Crawler $node): bool => str_contains($node->text(), $title),
        );
    }

    private function followedProduct(FixtureFactory $factory, string $title): Product
    {
        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => 100.0, 'baseQuantity' => 10, 'title' => $title],
        );
    }

    private function defaultSaleElements(Product $product): ProductSaleElements
    {
        $saleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne($this->getPropelConnection());

        self::assertInstanceOf(ProductSaleElements::class, $saleElements);

        return $saleElements;
    }

    /**
     * @param array{status?: string, expiresAt?: \DateTimeInterface} $overrides
     */
    private function subscription(
        FixtureFactory $factory,
        ProductSaleElements $saleElements,
        string $email,
        array $overrides = [],
    ): PriceDropAlert {
        $alert = (new PriceDropAlert())
            ->setProductSaleElementsId($saleElements->getId())
            ->setEmail($email)
            ->setLocale('en_US')
            ->setCurrencyId(Currency::getDefaultCurrency()->getId())
            ->setReferencePrice('100.000000')
            ->setStatus($overrides['status'] ?? PriceDropAlert::STATUS_ACTIVE)
            ->setExpiresAt($overrides['expiresAt'] ?? new \DateTimeImmutable('+30 days'));

        $alert->save($this->getPropelConnection());

        return $alert;
    }

    /**
     * Deliberately not createFixtureFactory(): that helper pushes a synthetic
     * request when the stack is empty, which would then be the main request the
     * security context reads its session from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
