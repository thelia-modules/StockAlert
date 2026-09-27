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

final readonly class SubscriptionRequest
{
    public function __construct(
        public int $productSaleElementsId,
        public string $email,
        public string $locale,
        public int $currencyId,
        public ?int $customerId = null,
    ) {
    }
}
