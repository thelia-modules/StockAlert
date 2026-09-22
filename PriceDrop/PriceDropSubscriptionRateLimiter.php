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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Two budgets, declared by the module in StockAlert::loadConfiguration(): one per
 * typed address, so nobody can make the shop fill somebody else's mailbox, and
 * one per caller, so one visitor cannot walk a list of addresses.
 */
final readonly class PriceDropSubscriptionRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.stockalert_price_drop_per_address')]
        private RateLimiterFactoryInterface $perAddressLimiter,
        #[Autowire(service: 'limiter.stockalert_price_drop_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function allows(string $email): bool
    {
        // No request in a CLI context: the per-client limit does not apply there.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp && !$this->perClientLimiter->create($clientIp)->consume()->isAccepted()) {
            return false;
        }

        return $this->perAddressLimiter->create(hash('sha256', mb_strtolower($email)))->consume()->isAccepted();
    }
}
