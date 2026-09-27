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

namespace StockAlert\Hook\Theme;

use StockAlert\PriceDrop\PriceDropConfig;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Two hooks answer here.
 *
 * `no.stock` is the historical one: a theme calls it in its out-of-stock branch
 * with the sale element id, and gets the restocking form.
 *
 * `product.pse.alerts` is the one Flexy calls inside its sale element selector,
 * on every variant, with `pseId`, `outOfStock` and the displayed `taxedPrice`:
 * it gets one block holding the restocking form when the variant is out of
 * stock and the price drop form when that feature is on. Being inside the
 * selector's live component, the block follows the selected variant.
 */
final readonly class StockAlertThemeHook implements ThemeHookInterface
{
    public const RESTOCKING_HOOK = 'no.stock';
    public const ALERTS_HOOK = 'product.pse.alerts';

    public function __construct(
        private Environment $twig,
        private PriceDropConfig $priceDropConfig,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName, [self::RESTOCKING_HOOK, self::ALERTS_HOOK], true);
    }

    public function render(string $hookName, array $parameters): string
    {
        $pseId = $parameters['pseId'] ?? null;

        if (!is_numeric($pseId)) {
            return '';
        }

        if (self::RESTOCKING_HOOK === $hookName) {
            return $this->twig->render('@StockAlertModule/theme_hook/stockAlert.html.twig', ['pseId' => (int) $pseId]);
        }

        $outOfStock = (bool) ($parameters['outOfStock'] ?? false);
        $priceDropEnabled = $this->priceDropConfig->isEnabled();

        if (!$outOfStock && !$priceDropEnabled) {
            return '';
        }

        $taxedPrice = $parameters['taxedPrice'] ?? null;

        return $this->twig->render('@StockAlertModule/theme_hook/productAlerts.html.twig', [
            'pseId' => (int) $pseId,
            'outOfStock' => $outOfStock,
            'priceDropEnabled' => $priceDropEnabled,
            'taxedPrice' => is_numeric($taxedPrice) ? (float) $taxedPrice : null,
        ]);
    }
}
