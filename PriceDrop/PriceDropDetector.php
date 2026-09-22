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

/**
 * Compares the current price of sale elements with what their subscribers saw,
 * and hands the significant drops to the sending queue. No email leaves here:
 * this runs inside the admin request that changed the price.
 */
final readonly class PriceDropDetector
{
    public function __construct(
        private PriceDropConfig $config,
        private EffectivePriceResolver $effectivePriceResolver,
    ) {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return int the number of subscriptions handed to the queue
     *
     * @throws PropelException
     */
    public function check(array $productSaleElementsIds): int
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return 0;
        }

        return $this->queueDrops(
            $this->activeAlerts()->filterByProductSaleElementsId($productSaleElementsIds, Criteria::IN)->find()->getData(),
        );
    }

    /**
     * Every active subscription, for the paths that write prices without an event:
     * the admin API, imports, currency rates, and a rule whose period just opened.
     *
     * @throws PropelException
     */
    public function checkAll(): int
    {
        return $this->queueDrops($this->activeAlerts()->find()->getData());
    }

    private function activeAlerts(): PriceDropAlertQuery
    {
        return PriceDropAlertQuery::create()
            ->filterByStatus(PriceDropAlert::STATUS_ACTIVE)
            ->filterByExpiresAt(new \DateTimeImmutable(), Criteria::GREATER_THAN);
    }

    /**
     * @param list<PriceDropAlert> $alerts
     *
     * @throws PropelException
     */
    private function queueDrops(array $alerts): int
    {
        if ([] === $alerts) {
            return 0;
        }

        $thresholdPercent = $this->config->thresholdPercent();
        $queued = 0;

        // One price resolution per (currency, customer) pair: a visitor's price may
        // depend on who they are, never on which subscription is being looked at.
        foreach ($this->groupByPricingContext($alerts) as $group) {
            $currency = CurrencyQuery::create()->findPk($group['currencyId']);

            if (null === $currency) {
                continue;
            }

            $customer = null !== $group['customerId'] ? CustomerQuery::create()->findPk($group['customerId']) : null;

            $currentPrices = $this->effectivePriceResolver->resolve(
                array_map(static fn (PriceDropAlert $alert): int => $alert->getProductSaleElementsId(), $group['alerts']),
                $currency,
                $customer,
            );

            foreach ($group['alerts'] as $alert) {
                $currentPrice = $currentPrices[$alert->getProductSaleElementsId()] ?? null;

                if (null === $currentPrice) {
                    continue;
                }

                if (!EffectivePriceResolver::isSignificantDrop((float) $alert->getReferencePrice(), $currentPrice, $thresholdPercent)) {
                    continue;
                }

                $queued += $this->queue($alert, $currentPrice);
            }
        }

        return $queued;
    }

    /**
     * A conditional update: two admin requests racing on the same price hand the
     * subscription to the queue once, whichever wins the row.
     *
     * @throws PropelException
     */
    private function queue(PriceDropAlert $alert, float $currentPrice): int
    {
        return PriceDropAlertQuery::create()
            ->filterById($alert->getId())
            ->filterByStatus(PriceDropAlert::STATUS_ACTIVE)
            ->update([
                'Status' => PriceDropAlert::STATUS_QUEUED,
                'NewPrice' => PriceDropSubscriptionService::decimal($currentPrice),
                'QueuedAt' => new \DateTimeImmutable(),
            ]);
    }

    /**
     * @param list<PriceDropAlert> $alerts
     *
     * @return array<string, array{currencyId: int, customerId: ?int, alerts: list<PriceDropAlert>}>
     */
    private function groupByPricingContext(array $alerts): array
    {
        $groups = [];

        foreach ($alerts as $alert) {
            $key = $alert->getCurrencyId().'|'.($alert->getCustomerId() ?? 0);
            $groups[$key] ??= ['currencyId' => $alert->getCurrencyId(), 'customerId' => $alert->getCustomerId(), 'alerts' => []];
            $groups[$key]['alerts'][] = $alert;
        }

        return $groups;
    }
}
