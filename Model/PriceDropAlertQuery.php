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

namespace StockAlert\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use StockAlert\Model\Base\PriceDropAlertQuery as BasePriceDropAlertQuery;
use StockAlert\Model\Map\PriceDropAlertTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'price_drop_alert' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PriceDropAlertQuery extends BasePriceDropAlertQuery
{
    /**
     * The subscriptions the detector compares: not expired, waiting for a drop.
     */
    public function active(): static
    {
        return $this
            ->filterByStatus(PriceDropAlert::STATUS_ACTIVE)
            ->filterByExpiresAt(new \DateTimeImmutable(), Criteria::GREATER_THAN);
    }

    /**
     * The subscriptions a shopper is still waiting on: not expired, waiting for
     * a drop or for the email to leave.
     */
    public function pending(): static
    {
        return $this
            ->filterByStatus([PriceDropAlert::STATUS_ACTIVE, PriceDropAlert::STATUS_QUEUED], Criteria::IN)
            ->filterByExpiresAt(new \DateTimeImmutable(), Criteria::GREATER_THAN);
    }

    /**
     * The products still followed, one row each. A queued subscription is still
     * a subscription (its email is written, not sent), so it counts in the total
     * and again in its own column.
     */
    public function countFollowedProducts(): int
    {
        return \count(
            $this->pending()
                ->groupedByProduct()
                ->select(['product_id'])
                ->find(),
        );
    }

    /**
     * One page of the followed products, the most followed first.
     *
     * @return list<array{product_id: int, subscription_count: int, queued_count: int}>
     */
    public function followedProductsPage(int $page, int $pageSize): array
    {
        $groups = $this->pending()
            ->groupedByProduct()
            ->withColumn('COUNT('.PriceDropAlertTableMap::COL_ID.')', 'subscription_count')
            ->withColumn(
                'SUM(CASE WHEN '.PriceDropAlertTableMap::COL_STATUS." = '".PriceDropAlert::STATUS_QUEUED."' THEN 1 ELSE 0 END)",
                'queued_count',
            )
            ->orderBy('subscription_count', Criteria::DESC)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->select(['product_id', 'subscription_count', 'queued_count'])
            ->find();

        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                'product_id' => (int) $group['product_id'],
                // MySQL hands COUNT() and SUM() back as strings.
                'subscription_count' => (int) $group['subscription_count'],
                'queued_count' => (int) $group['queued_count'],
            ];
        }

        return $rows;
    }

    private function groupedByProduct(): static
    {
        return $this
            ->joinProductSaleElements()
            ->withColumn(ProductSaleElementsTableMap::COL_PRODUCT_ID, 'product_id')
            ->groupBy(ProductSaleElementsTableMap::COL_PRODUCT_ID);
    }
}
