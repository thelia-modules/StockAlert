<?php

declare(strict_types=1);

namespace StockAlert\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use StockAlert\Model\Base\PriceDropAlertQuery as BasePriceDropAlertQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'price_drop_alert' table.
 *
 *
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

}
