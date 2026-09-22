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

namespace StockAlert\PriceDrop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Psr\Log\LoggerInterface;
use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\StockAlert;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Tools\URL;

/**
 * Sends the emails the detector queued, a bounded batch at a time, from the
 * cron command: never from the request that changed a price.
 */
final readonly class PriceDropQueueProcessor
{
    /** After this many failed sends the subscription is dropped: the address is most likely dead. */
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private MailerFactory $mailer,
        private URL $url,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int the number of emails sent
     *
     * @throws PropelException
     */
    public function drain(int $limit): int
    {
        $sent = 0;

        /** @var PriceDropAlert $alert */
        foreach ($this->queued($limit) as $alert) {
            if ($this->send($alert)) {
                ++$sent;
            }
        }

        return $sent;
    }

    /**
     * Subscriptions nobody will ever hear about again: expired while waiting.
     *
     * @return int the number of subscriptions removed
     *
     * @throws PropelException
     */
    public function purge(?\DateTimeInterface $now = null): int
    {
        return PriceDropAlertQuery::create()
            ->filterByStatus(PriceDropAlert::STATUS_ACTIVE)
            ->filterByExpiresAt($now ?? new \DateTimeImmutable(), Criteria::LESS_EQUAL)
            ->delete();
    }

    /**
     * @return iterable<PriceDropAlert>
     */
    private function queued(int $limit): iterable
    {
        return PriceDropAlertQuery::create()
            ->filterByStatus(PriceDropAlert::STATUS_QUEUED)
            ->orderByQueuedAt(Criteria::ASC)
            ->orderById(Criteria::ASC)
            ->limit(max(1, $limit))
            ->find();
    }

    /**
     * @throws PropelException
     */
    private function send(PriceDropAlert $alert): bool
    {
        // Both rows are there as long as the alert is: the foreign keys cascade.
        $productSaleElements = $alert->getProductSaleElements();
        $currency = $alert->getCurrency();

        try {
            $this->mailer->sendEmailMessageOrFail(
                StockAlert::MESSAGE_PRICE_DROP,
                [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
                [$alert->getEmail() => $alert->getEmail()],
                $this->messageParameters($alert, $productSaleElements, $currency),
                $alert->getLocale(),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Price drop alert email could not be sent.', [
                'price_drop_alert_id' => $alert->getId(),
                'exception' => $exception,
            ]);

            $alert->setAttempts($alert->getAttempts() + 1);

            if ($alert->getAttempts() >= self::MAX_ATTEMPTS) {
                $alert->delete();

                return false;
            }

            $alert->save();

            return false;
        }

        // Consumed: a visitor who wants to follow the next drop subscribes again.
        $alert->delete();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function messageParameters(PriceDropAlert $alert, ProductSaleElements $productSaleElements, Currency $currency): array
    {
        $locale = $alert->getLocale() ?? 'en_US';
        $product = $productSaleElements->getProduct();
        $shopCountry = Country::getShopLocation();

        $referencePrice = (float) $alert->getReferencePrice();
        $newPrice = (float) $alert->getNewPrice();

        return [
            'locale' => $locale,
            'product_id' => $product->getId(),
            'pse_id' => $productSaleElements->getId(),
            'product_title' => $this->productTitle($product, $locale),
            'product_url' => $this->productUrl($product->getId(), $locale),
            'currency_id' => $currency->getId(),
            'currency_code' => $currency->getCode(),
            'currency_symbol' => $currency->getSymbol(),
            'old_untaxed_price' => $referencePrice,
            'new_untaxed_price' => $newPrice,
            'old_price' => $this->taxed($productSaleElements, $referencePrice, $shopCountry),
            'new_price' => $this->taxed($productSaleElements, $newPrice, $shopCountry),
        ];
    }

    /**
     * The title in the subscriber's language, else in the shop's default one, else the reference.
     */
    private function productTitle(Product $product, string $locale): string
    {
        foreach (array_unique([$locale, Lang::getDefaultLanguage()->getLocale()]) as $candidate) {
            $title = trim((string) $product->setLocale($candidate)->getTitle());

            if ('' !== $title) {
                return $title;
            }
        }

        return (string) $product->getRef();
    }

    private function taxed(ProductSaleElements $productSaleElements, float $untaxedPrice, Country $country): float
    {
        // getTaxedPrice() reads the price from a loop virtual column: hand it the one to tax.
        $productSaleElements->setVirtualColumn('price_PRICE', $untaxedPrice);

        return round($productSaleElements->getTaxedPrice($country), 2);
    }

    private function productUrl(int $productId, string $locale): string
    {
        try {
            return $this->url->retrieve('product', $productId, $locale)->toString();
        } catch (\Throwable) {
            return $this->url->absoluteUrl('/product/'.$productId);
        }
    }
}
