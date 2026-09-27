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

use Psr\Log\NullLogger;
use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\PriceDrop\EffectivePriceResolver;
use StockAlert\PriceDrop\PriceDropQueueProcessor;
use StockAlert\PriceDrop\PriceDropSubscriptionService;
use StockAlert\StockAlert;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ProductSaleElements;
use Thelia\Tools\URL;

final class PriceDropQueueProcessorTest extends PriceDropTestCase
{
    private RecordingPriceDropMailer $mailer;
    private PriceDropQueueProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new RecordingPriceDropMailer(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
        $this->processor = new PriceDropQueueProcessor($this->mailer, $this->getService(URL::class), new NullLogger(), $this->getService(EffectivePriceResolver::class));
    }

    public function testAQueuedAlertIsMailedWithBothPricesThenConsumed(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->queuedAlert($productSaleElements, 'happy@example.com', 40.0, 32.0);
        $this->repriceDefaultCurrency($productSaleElements, 32.0);

        self::assertSame(1, $this->processor->drain(10));

        self::assertCount(1, $this->mailer->sent);
        $message = $this->mailer->sent[0];
        self::assertSame(StockAlert::MESSAGE_PRICE_DROP, $message['code']);
        self::assertSame(['happy@example.com' => 'happy@example.com'], $message['to']);
        self::assertSame('fr_FR', $message['locale']);
        self::assertSame('Followed product', $message['parameters']['product_title']);
        self::assertSame(40.0, $message['parameters']['old_untaxed_price']);
        self::assertSame(32.0, $message['parameters']['new_untaxed_price']);
        self::assertGreaterThanOrEqual(40.0, $message['parameters']['old_price']);
        self::assertGreaterThanOrEqual(32.0, $message['parameters']['new_price']);
        self::assertLessThan($message['parameters']['old_price'], $message['parameters']['new_price']);
        self::assertStringContainsString('http', $message['parameters']['product_url']);
        self::assertMatchesRegularExpression('/[?&]ref='.preg_quote(rawurlencode((string) $productSaleElements->getRef()), '/').'$/', $message['parameters']['product_url'], 'the link opens the page on the followed variant');
        self::assertSame($this->currency->getId(), $message['parameters']['currency_id']);

        self::assertNull(PriceDropAlertQuery::create()->findPk($alert->getId()));
    }

    public function testTheBatchIsBoundedAndTheOldestGoFirst(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $this->repriceDefaultCurrency($productSaleElements, 30.0);
        $this->queuedAlert($productSaleElements, 'first@example.com', 40.0, 30.0, new \DateTimeImmutable('-3 minutes'));
        $this->queuedAlert($productSaleElements, 'second@example.com', 40.0, 30.0, new \DateTimeImmutable('-2 minutes'));
        $third = $this->queuedAlert($productSaleElements, 'third@example.com', 40.0, 30.0, new \DateTimeImmutable('-1 minute'));

        self::assertSame(2, $this->processor->drain(2));

        self::assertSame(['first@example.com', 'second@example.com'], array_map(static fn (array $m): string => array_key_first($m['to']), $this->mailer->sent));
        self::assertSame(PriceDropAlert::STATUS_QUEUED, PriceDropAlertQuery::create()->findPk($third->getId())?->getStatus());
    }

    public function testActiveSubscriptionsAreNeverMailed(): void
    {
        $this->activeAlert($this->productPricedAt(40.0)->getDefaultSaleElements(), 'waiting@example.com', 40.0);

        self::assertSame(0, $this->processor->drain(10));
        self::assertSame([], $this->mailer->sent);
    }

    public function testAFailedSendIsRetriedThenGivenUp(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $this->repriceDefaultCurrency($productSaleElements, 30.0);
        $alert = $this->queuedAlert($productSaleElements, 'dead@example.com', 40.0, 30.0);
        $this->mailer->failing = true;

        self::assertSame(0, $this->processor->drain(10));
        self::assertSame(1, PriceDropAlertQuery::create()->findPk($alert->getId())?->getAttempts());
        self::assertSame(0, $this->processor->drain(10));
        self::assertSame(2, PriceDropAlertQuery::create()->findPk($alert->getId())?->getAttempts());
        self::assertSame(0, $this->processor->drain(10));
        self::assertNull(PriceDropAlertQuery::create()->findPk($alert->getId()));
    }

    public function testADropUndoneBeforeTheSendPutsTheSubscriptionBackToWaiting(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $alert = $this->queuedAlert($productSaleElements, 'patient@example.com', 40.0, 32.0);

        self::assertSame(0, $this->processor->drain(10));

        self::assertSame([], $this->mailer->sent, 'the sale element is back at 40: there is no drop to announce');
        $row = PriceDropAlertQuery::create()->findPk($alert->getId());
        self::assertSame(PriceDropAlert::STATUS_ACTIVE, $row?->getStatus());
        self::assertNull($row?->getNewPrice());
        self::assertNull($row?->getQueuedAt());
    }

    public function testTheEmailAnnouncesThePriceChargedWhenItLeaves(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $this->queuedAlert($productSaleElements, 'lucky@example.com', 40.0, 32.0);
        $this->repriceDefaultCurrency($productSaleElements, 28.0);

        self::assertSame(1, $this->processor->drain(10));

        self::assertSame(28.0, $this->mailer->sent[0]['parameters']['new_untaxed_price']);
    }

    public function testPurgeRemovesTheExpiredSubscriptionsOnly(): void
    {
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $expired = $this->activeAlert($productSaleElements, 'old@example.com', 40.0, ['expiresAt' => new \DateTimeImmutable('-7 months')]);
        $fresh = $this->activeAlert($productSaleElements, 'fresh@example.com', 40.0);
        $queuedButOld = $this->queuedAlert($productSaleElements, 'queued@example.com', 40.0, 30.0, expiresAt: new \DateTimeImmutable('-1 day'));

        self::assertSame(1, $this->processor->purge());

        self::assertNull(PriceDropAlertQuery::create()->findPk($expired->getId()));
        self::assertNotNull(PriceDropAlertQuery::create()->findPk($fresh->getId()));
        self::assertNotNull(PriceDropAlertQuery::create()->findPk($queuedButOld->getId()));
    }

    private function queuedAlert(
        ProductSaleElements $productSaleElements,
        string $email,
        float $referencePrice,
        float $newPrice,
        ?\DateTimeImmutable $queuedAt = null,
        ?\DateTimeImmutable $expiresAt = null,
    ): PriceDropAlert {
        $alert = $this->activeAlert($productSaleElements, $email, $referencePrice, [
            'status' => PriceDropAlert::STATUS_QUEUED,
            'expiresAt' => $expiresAt ?? new \DateTimeImmutable('+30 days'),
        ]);
        $alert
            ->setLocale('fr_FR')
            ->setNewPrice(PriceDropSubscriptionService::decimal($newPrice))
            ->setQueuedAt($queuedAt ?? new \DateTimeImmutable())
            ->save();

        return $alert;
    }
}
