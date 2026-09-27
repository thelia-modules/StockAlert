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
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleProduct;
use Thelia\Model\SaleProductQuery;

/**
 * The sale elements a sale operation reprices: every element of a selected
 * product, or only those carrying the selected attribute value. Mirrors the
 * selection Thelia\Action\Sale applies when it writes the promo prices.
 */
final readonly class SaleProductSaleElementsFinder
{
    /**
     * @return list<int>
     *
     * @throws PropelException
     */
    public function productSaleElementsIdsOf(Sale $sale): array
    {
        $ids = [];

        /** @var SaleProduct $saleProduct */
        foreach (SaleProductQuery::create()->filterBySale($sale)->find() as $saleProduct) {
            $query = ProductSaleElementsQuery::create()
                ->filterByProductId($saleProduct->getProductId())
                ->select(['Id']);

            if (null !== $saleProduct->getAttributeAvId()) {
                $query
                    ->useAttributeCombinationQuery(null, Criteria::LEFT_JOIN)
                    ->filterByAttributeAvId($saleProduct->getAttributeAvId())
                    ->endUse();
            }

            foreach ($query->find() as $id) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }
}
