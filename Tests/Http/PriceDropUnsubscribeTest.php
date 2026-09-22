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
use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\PriceDrop\UnsubscribeTokenSigner;
use Thelia\Model\Currency;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The unsubscribe link works for a visitor with no account and no session, and
 * answers the same page whatever the token names.
 */
final class PriceDropUnsubscribeTest extends WebIntegrationTestCase
{
    private const PATH = '/module/stockalert/price-drop/unsubscribe/';

    private FixtureFactory $factory;
    private UnsubscribeTokenSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->signer = $this->getService(UnsubscribeTokenSigner::class);
        // The link answers with one redirect before the page, whatever the token.
        $this->client->followRedirects(true);
    }

    public function testAValidLinkRemovesTheSubscriptionWithoutAnySession(): void
    {
        $alert = $this->alert();
        $token = $this->signer->sign($alert->getId(), new \DateTimeImmutable('+30 days'));

        $this->assertPageRenders(self::PATH.$token);
        $first = (string) $this->client->getResponse()->getContent();

        self::assertNull(PriceDropAlertQuery::create()->findPk($alert->getId()));
        self::assertStringContainsString('Price alert cancelled', $first);

        // The second click, on a subscription that is gone, gets the very same page.
        $this->assertPageRenders(self::PATH.$token);
        self::assertSame($this->pageBody($first), $this->pageBody((string) $this->client->getResponse()->getContent()));
    }

    public function testAForgedOrExpiredLinkRemovesNothingAndLooksTheSame(): void
    {
        $alert = $this->alert();
        $expired = $this->signer->sign($alert->getId(), new \DateTimeImmutable('-1 minute'));
        $forged = (new UnsubscribeTokenSigner('not-the-shop-secret'))->sign($alert->getId(), new \DateTimeImmutable('+30 days'));

        foreach ([$expired, $forged, 'garbage'] as $token) {
            $this->assertPageRenders(self::PATH.$token);
            self::assertStringContainsString('Price alert cancelled', (string) $this->client->getResponse()->getContent());
        }

        self::assertNotNull(PriceDropAlertQuery::create()->findPk($alert->getId()));
    }

    private function alert(): PriceDropAlert
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => 40.0]);
        $alert = (new PriceDropAlert())
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setEmail(\sprintf('unsubscribe-%s@example.com', bin2hex(random_bytes(4))))
            ->setLocale('en_US')
            ->setCurrencyId(Currency::getDefaultCurrency()->getId())
            ->setReferencePrice('40.000000')
            ->setStatus(PriceDropAlert::STATUS_ACTIVE)
            ->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $alert->save();

        return $alert;
    }

    /**
     * The body without what legitimately differs between two renders (tokens, nonces).
     */
    private function pageBody(string $html): string
    {
        return preg_replace('/(name="_token" value=")[^"]*/', '$1', substr($html, (int) strpos($html, '<main')) ?: $html) ?? '';
    }
}
