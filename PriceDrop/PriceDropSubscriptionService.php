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
use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class PriceDropSubscriptionService
{
    public function __construct(
        private PriceDropConfig $config,
        private PriceDropSubscriptionRateLimiter $rateLimiter,
        private EffectivePriceResolver $effectivePriceResolver,
    ) {
    }

    /**
     * Records what the visitor saw. An address already following this sale element
     * is left as it is, with its first reference price: the caller answers the same
     * way in both cases, so the form never tells whether an address is known.
     *
     * @return float the untaxed reference price recorded, or already recorded, for this address
     *
     * @throws SubscriptionRefusedException
     * @throws PropelException
     */
    public function subscribe(SubscriptionRequest $request): float
    {
        if (!$this->config->isEnabled()) {
            throw new SubscriptionRefusedException(SubscriptionRefusal::Disabled);
        }

        $email = mb_strtolower(trim($request->email));

        // Taken before anything is looked up, so a caller spends the same budget
        // whatever the outcome.
        if (!$this->rateLimiter->allows($email)) {
            throw new SubscriptionRefusedException(SubscriptionRefusal::RateLimited);
        }

        $productSaleElements = ProductSaleElementsQuery::create()->findPk($request->productSaleElementsId);

        if (null === $productSaleElements) {
            throw new SubscriptionRefusedException(SubscriptionRefusal::UnknownProductSaleElement);
        }

        $currency = CurrencyQuery::create()->findPk($request->currencyId);

        if (null === $currency) {
            throw new SubscriptionRefusedException(SubscriptionRefusal::UnknownCurrency);
        }

        $existing = PriceDropAlertQuery::create()
            ->filterByProductSaleElementsId($productSaleElements->getId())
            ->filterByEmail($email)
            ->findOne();

        if (null !== $existing) {
            return (float) $existing->getReferencePrice();
        }

        $activeCount = PriceDropAlertQuery::create()
            ->filterByEmail($email)
            ->filterByStatus(PriceDropAlert::STATUS_ACTIVE)
            ->filterByExpiresAt(new \DateTimeImmutable(), Criteria::GREATER_THAN)
            ->count();

        if ($activeCount >= $this->config->maxSubscriptionsPerEmail()) {
            throw new SubscriptionRefusedException(SubscriptionRefusal::QuotaExceeded);
        }

        $customer = null !== $request->customerId ? CustomerQuery::create()->findPk($request->customerId) : null;
        $referencePrice = $this->effectivePriceResolver->resolveOne($productSaleElements->getId(), $currency, $customer);

        if (null === $referencePrice) {
            throw new SubscriptionRefusedException(SubscriptionRefusal::UnknownProductSaleElement);
        }

        (new PriceDropAlert())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setCustomerId($customer?->getId())
            ->setEmail($email)
            ->setLocale($request->locale)
            ->setCurrencyId($currency->getId())
            ->setReferencePrice(self::decimal($referencePrice))
            ->setStatus(PriceDropAlert::STATUS_ACTIVE)
            ->setAttempts(0)
            ->setExpiresAt(new \DateTimeImmutable(\sprintf('+%d days', $this->config->expirationDays())))
            ->save();

        return $referencePrice;
    }

    /**
     * Deletes one subscription. Nothing is reported when it does not exist any
     * more: an unsubscribe link must not reveal whether it was still valid.
     *
     * @throws PropelException
     */
    public function unsubscribe(int $priceDropAlertId): void
    {
        PriceDropAlertQuery::create()->filterById($priceDropAlertId)->delete();
    }

    public static function decimal(float $price): string
    {
        return number_format($price, 6, '.', '');
    }
}
