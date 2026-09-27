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

use StockAlert\StockAlert;
use Thelia\Model\ConfigQuery;

final readonly class PriceDropConfig
{
    public function isEnabled(): bool
    {
        return '1' === ConfigQuery::read(StockAlert::CONFIG_PRICE_DROP_ENABLED, StockAlert::DEFAULT_PRICE_DROP_ENABLED);
    }

    public function thresholdPercent(): float
    {
        return max(0.0, (float) ConfigQuery::read(
            StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT,
            StockAlert::DEFAULT_PRICE_DROP_THRESHOLD_PERCENT,
        ));
    }

    public function expirationDays(): int
    {
        return max(1, (int) ConfigQuery::read(
            StockAlert::CONFIG_PRICE_DROP_EXPIRATION_DAYS,
            StockAlert::DEFAULT_PRICE_DROP_EXPIRATION_DAYS,
        ));
    }

    public function maxSubscriptionsPerEmail(): int
    {
        return max(1, (int) ConfigQuery::read(
            StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL,
            StockAlert::DEFAULT_PRICE_DROP_MAX_PER_EMAIL,
        ));
    }

    public function batchSize(): int
    {
        return max(1, (int) ConfigQuery::read(
            StockAlert::CONFIG_PRICE_DROP_BATCH_SIZE,
            StockAlert::DEFAULT_PRICE_DROP_BATCH_SIZE,
        ));
    }
}
