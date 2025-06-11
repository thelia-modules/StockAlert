<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Twig\Plugins;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StockAlertPluginTwig extends AbstractExtension
{
    public function __construct(
        private readonly Environment $twig
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('onProductDetailsBottom', [$this, 'getOnProductDetailsBottom']),
            new TwigFunction('onProductJavascriptInitialization', [$this, 'getOnProductJavascriptInitialization'])
        ];
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function getOnProductDetailsBottom(): string
    {
        return $this->twig->render('product-details-bottom.html.twig');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getOnProductJavascriptInitialization(): string
    {
        return $this->twig->render('product-javascript-initialization.html.twig');
    }
}
