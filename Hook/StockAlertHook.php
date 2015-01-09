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

use SplitShipment\SplitShipment;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class StockAlertHook
 * @package StockAlert\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class StockAlertHook extends BaseHook
{

    public function onProductDetailsBottom(HookRenderEvent $event)
    {
        $config = SplitShipment::getConfig();

        $event->add(
            $this->render(
                "product-details-bottom.html"
            )
        );
    }

    public function onProductJavascriptInitialization(HookRenderEvent $event)
    {
        $config = SplitShipment::getConfig();

        $event->add(
            $this->render(
                "product.javascript-initialization.html"
            )
        );
    }

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $moduleId = $this->getModule()->getModuleId();
        $config = SplitShipment::getConfig();

        $event->add(
            $this->render(
                "configuration.html",
                [
                    'module_id' => $moduleId,
                    'method' => $config['method'],
                    'config' => $config
                ]
            )
        );
    }

}
