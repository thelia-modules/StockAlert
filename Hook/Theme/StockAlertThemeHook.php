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

use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Renders the restocking alert subscription form under the PSE selector, on the
 * combinations the theme reports as out of stock.
 *
 * The theme must pass the current PSE id at the hook point:
 * theme_hook('pse.selector.bottom', {pseId: currentPse.id})
 */
final readonly class StockAlertThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return 'no.stock' === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        $pseId = $parameters['pseId'] ?? null;

        if (!is_numeric($pseId)) {
            return '';
        }

        return $this->twig->render('@StockAlertModule/theme_hook/stockAlert.html.twig', [
            'pseId' => (int) $pseId,
        ]);
    }
}
