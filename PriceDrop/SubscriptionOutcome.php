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

/**
 * What a subscription request leaves the caller with: the row that now, or
 * already, follows the sale element for this address, and the untaxed price it
 * compares against.
 */
final readonly class SubscriptionOutcome
{
    public function __construct(
        public int $priceDropAlertId,
        public float $untaxedReferencePrice,
        public \DateTimeInterface $expiresAt,
    ) {
    }
}
