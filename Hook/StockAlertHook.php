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


namespace StockAlert\Hook;

use StockAlert\StockAlert;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\ExportQuery;

/**
 * Class StockAlertHook
 * @package StockAlert\Hook
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertHook extends BaseHook
{

    public function onProductDetailsBottom(HookRenderEvent $event)
    {
        $event->add(
            $this->render(
                "product-details-bottom.html"
            )
        );
    }

    public function onProductJavascriptInitialization(HookRenderEvent $event)
    {
        $event->add(
            $this->render(
                "product.javascript-initialization.html"
            )
        );
    }

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $moduleId = $this->getModule()->getModuleId();
        $config = StockAlert::getConfig();

        $stockAlertExport = ExportQuery::create()->filterByRef('stockalert.export.stockAlert')->findOne();

        $event->add(
            $this->render(
                "configuration.html",
                [
                    'module_id' => $moduleId,
                    'config' => $config,
                    'export_stock_alert_id' => $stockAlertExport->getId(),
                ]
            )
        );
    }
}
