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

use StockAlert\Model\RestockingAlertQuery;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Shared ground for the restocking alert tests: a product out of stock to wait for, the settings put back
 * after, a mailer that keeps what it is asked to send, and a caller address of its own.
 */
abstract class RestockingTestCase extends IntegrationTestCase
{
    protected FixtureFactory $factory;

    protected RecordingRestockingMailer $mailer;

    /** @var array<string, ?string> */
    private array $previousConfig = [];

    private ?string $previousClientIp = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->mailer = new RecordingRestockingMailer(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        $this->writeConfig('store_email', 'shop@example.com');
        $this->writeConfig('store_name', 'The shop');

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

    /**
     * A product whose only sale element is out of stock.
     */
    protected function productOutOfStock(string $title = 'Sold out product'): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => 20.0, 'baseQuantity' => 0, 'title' => $title],
        );
    }

    protected function uniqueEmail(string $prefix = 'shopper'): string
    {
        return \sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(6)));
    }

    protected function subscriptionsOf(int $productSaleElementsId): int
    {
        return RestockingAlertQuery::create()->filterByProductSaleElementsId($productSaleElementsId)->count();
    }
}
