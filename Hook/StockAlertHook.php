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

/*      Copyright (c) OpenStudio */
/*      email : dev@thelia.net */
/*      web : http://www.thelia.net */

/*      For the full copyright and license information, please view the LICENSE.txt */
/*      file that was distributed with this source code. */

namespace StockAlert\Hook;

use StockAlert\Form\StockAlertConfig;
use StockAlert\Model\RestockingAlertQuery;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class StockAlertHook.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'product.stock-alert' => [
                ['type' => 'front', 'method' => 'onProductDetailsBottom'],
            ],
            'product.details-bottom' => [
                ['type' => 'front', 'method' => 'onProductDetailsBottom'],
            ],
            'product.javascript-initialization' => [
                ['type' => 'front', 'method' => 'onProductJavascriptInitialization'],
            ],
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    public function onProductDetailsBottom(HookRenderEvent $event): void
    {
        $event->add(
            $this->render(
                'product-details-bottom.html'
            )
        );
    }

    public function onProductJavascriptInitialization(HookRenderEvent $event): void
    {
        $event->add(
            $this->render(
                'product.javascript-initialization.html'
            )
        );
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(StockAlertConfig::getName());
        $event->add(
            $this->render(
                'StockAlert/configuration.html.twig',
                [
                    'form' => $form->createView()->getView(),
                    'stock_check_enabled' => '1' === ConfigQuery::read('check-available-stock'),
                    'subscriptions' => $this->getSubscriptions(),
                ]
            )
        );
    }

    /**
     * Reproduces the legacy {loop type="restocking-alert"} + nested
     * product_sale_elements / product loops, exposing a Twig-friendly array.
     *
     * @return array<int, array{id:int, email:?string, createDate:?\DateTime, productId:?int, productTitle:?string}>
     */
    private function getSubscriptions(): array
    {
        $request = $this->getRequest();
        $locale = $request !== null ? $request->getLocale() : 'en_US';
        $alerts = RestockingAlertQuery::create()
            ->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)
            ->find();

        $subscriptions = [];

        foreach ($alerts as $alert) {
            $productId = null;
            $productTitle = null;

            $pse = ProductSaleElementsQuery::create()->findPk($alert->getProductSaleElementsId());
            if ($pse !== null) {
                $product = ProductQuery::create()->findPk($pse->getProductId());
                if ($product !== null) {
                    $productId = $product->getId();
                    $productTitle = $product->setLocale($locale)->getTitle();
                }
            }

            $subscriptions[] = [
                'id' => $alert->getId(),
                'email' => $alert->getEmail(),
                'createDate' => $alert->getCreatedAt(),
                'productId' => $productId,
                'productTitle' => $productTitle,
            ];
        }
        return $subscriptions;
    }
}
