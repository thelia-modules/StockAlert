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

namespace StockAlert\EventListeners;

use Psr\Log\LoggerInterface;
use StockAlert\PriceDrop\PriceDropConfig;
use StockAlert\PriceDrop\PriceDropDetector;
use StockAlert\PriceDrop\SaleProductSaleElementsFinder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\Sale\ProductSaleStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Listens after the core actions (priority 128) have written the new prices, so
 * the detector reads what the shop now charges. Nothing here may throw: the
 * sale events are dispatched inside the operation's own transaction, and an
 * exception would roll the shopkeeper's change back.
 */
final readonly class PriceDropAlertListener implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    public function __construct(
        private PriceDropConfig $config,
        private PriceDropDetector $detector,
        private SaleProductSaleElementsFinder $saleProductSaleElementsFinder,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => ['onProductSaleElementUpdated', self::PRIORITY],
            TheliaEvents::UPDATE_PRODUCT_SALE_STATUS => ['onSaleStatusUpdated', self::PRIORITY],
            TheliaEvents::CATALOG_PRICE_RULE_CREATE => ['onCatalogPriceRuleChanged', self::PRIORITY],
            TheliaEvents::CATALOG_PRICE_RULE_UPDATE => ['onCatalogPriceRuleChanged', self::PRIORITY],
            TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY => ['onCatalogPriceRuleChanged', self::PRIORITY],
            TheliaEvents::CATALOG_PRICE_RULE_RECOMPUTE => ['onCatalogPriceRuleChanged', self::PRIORITY],
        ];
    }

    public function onProductSaleElementUpdated(ProductSaleElementUpdateEvent $event): void
    {
        $this->guard(fn (): int => $this->detector->check([$event->getProductSaleElementId()]));
    }

    public function onSaleStatusUpdated(ProductSaleStatusUpdateEvent $event): void
    {
        $sale = $event->getSale();

        // Closing an operation raises prices; a reserved one writes no catalog price.
        if (null === $sale || !$sale->getActive() || $sale->isReserved()) {
            return;
        }

        $this->guard(fn (): int => $this->detector->check($this->saleProductSaleElementsFinder->productSaleElementsIdsOf($sale)));
    }

    public function onCatalogPriceRuleChanged(CatalogPriceRuleEvent $event): void
    {
        // A rule prices by scope, not by sale element: every subscription is looked at,
        // in one batched resolution.
        $this->guard(fn (): int => $this->detector->checkAll());
    }

    /**
     * @param callable(): int $detection
     */
    private function guard(callable $detection): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $detection();
        } catch (\Throwable $exception) {
            $this->logger->error('Price drop detection failed; the price change itself is kept.', [
                'exception' => $exception,
            ]);
        }
    }
}
