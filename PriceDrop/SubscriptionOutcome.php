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
 *
 * `ownedByCaller` says whether the caller may hold the unsubscribe link of that
 * row: it created it, or it is the customer the row is attached to. A row that
 * was already there for an address anyone can type belongs to whoever typed it
 * first, not to the visitor of this request.
 */
final readonly class SubscriptionOutcome
{
    public function __construct(
        public int $priceDropAlertId,
        public float $untaxedReferencePrice,
        public \DateTimeInterface $expiresAt,
        public bool $ownedByCaller,
    ) {
    }
}
