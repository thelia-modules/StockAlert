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
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The untaxed price a visitor pays right now for a sale element: a catalog price
 * rule or a reserved operation when the core prices one, otherwise the catalog
 * columns, promo price when the sale element is on promotion.
 */
final readonly class EffectivePriceResolver
{
    /**
     * Two prices closer than this are the same price: DECIMAL(16,6) columns and
     * currency conversions never agree beyond the sixth decimal.
     */
    public const EPSILON = 0.000001;

    public function __construct(private EffectivePriceCatalog $effectivePriceCatalog)
    {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, float> untaxed price keyed by sale element id; a sale element without price is absent
     */
    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer = null): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $prices = [];

        foreach ($this->effectivePriceCatalog->warm($productSaleElementsIds, $currency, $customer) as $pseId => $effectivePrice) {
            $prices[$pseId] = $effectivePrice->untaxedPromoPrice;
        }

        $missing = array_values(array_diff($productSaleElementsIds, array_keys($prices)));

        /** @var ProductSaleElements $productSaleElements */
        foreach (ProductSaleElementsQuery::create()->filterById($missing, Criteria::IN)->find() as $productSaleElements) {
            try {
                $catalogPrices = $productSaleElements->getPricesByCurrency($currency);
            } catch (\RuntimeException) {
                // No price at all in the default currency: nothing to compare against.
                continue;
            }

            $prices[$productSaleElements->getId()] = $productSaleElements->getPromo()
                ? $catalogPrices->getPromoPrice()
                : $catalogPrices->getPrice();
        }

        return $prices;
    }

    public function resolveOne(int $productSaleElementsId, Currency $currency, ?Customer $customer = null): ?float
    {
        return $this->resolve([$productSaleElementsId], $currency, $customer)[$productSaleElementsId] ?? null;
    }

    /**
     * A drop counts when the current price is below the reference by at least the
     * threshold, expressed as a percentage of the reference. A threshold of zero
     * keeps a guard against rounding noise.
     */
    public static function isSignificantDrop(float $referencePrice, float $currentPrice, float $thresholdPercent): bool
    {
        $drop = $referencePrice - $currentPrice;

        if ($drop <= self::EPSILON) {
            return false;
        }

        return $drop + self::EPSILON >= $referencePrice * max(0.0, $thresholdPercent) / 100;
    }
}
