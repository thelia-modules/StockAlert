<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace StockAlert\Event;

/**
 * Class StockAlertEvents
 * @package RestockingAlert\Event
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 *
 */
class StockAlertEvents
{
    const STOCK_ALERT_SUBSCRIBE = "stockalert.subscribe";
    const STOCK_ALERT_CHECK_AVAILABILITY = "stockalert.check.availability";
}
