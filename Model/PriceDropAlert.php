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

use StockAlert\Model\Base\PriceDropAlert as BasePriceDropAlert;

class PriceDropAlert extends BasePriceDropAlert
{
    /** Waiting for the price to drop. */
    public const STATUS_ACTIVE = 'active';

    /** The drop was seen, the email is waiting to be sent by the cron command. */
    public const STATUS_QUEUED = 'queued';
}
