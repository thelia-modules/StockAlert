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

namespace StockAlert\Exception;

/**
 * A subscription the shop refuses for a reason the visitor may be told (the size is in stock, the address
 * is already registered, too many requests...), thrown by a listener of the subscription event.
 *
 * Its message is shown to the visitor as it is: it must be written for them, in their language, and say
 * nothing of the shop's internals. It is the only exception whose message the module shows; any other
 * failure is logged and shown as a generic message.
 */
final class SubscriptionRefusedException extends \RuntimeException
{
}
