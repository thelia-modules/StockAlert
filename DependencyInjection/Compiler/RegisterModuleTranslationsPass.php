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

namespace StockAlert\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hands the module's I18n catalogs to the framework translator, the one behind
 * the Twig |trans filter on the front and in emails.
 *
 * The kernel registers those files with Thelia's own translator only, which the
 * back office reaches through a decorator on admin requests. Everywhere else the
 * native filter sees the framework translator alone, and a module template would
 * render its source strings. Same files, same domain names as Thelia's
 * (stockalert, stockalert.email.default, stockalert.fo.<theme>, stockalert.bo.<theme>).
 */
final readonly class RegisterModuleTranslationsPass implements CompilerPassInterface
{
    private const TEMPLATE_SCOPES = ['email' => 'email', 'frontOffice' => 'fo', 'backOffice' => 'bo'];

    public function __construct(
        private string $moduleDirectory,
        private string $domain,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $translator = $container->getDefinition('translator.default');
        $i18nDirectory = $this->moduleDirectory.'/I18n';

        if (!is_dir($i18nDirectory)) {
            return;
        }

        $container->addResource(new GlobResource($i18nDirectory, '/**/*.php', true));

        foreach (glob($i18nDirectory.'/*.php') ?: [] as $file) {
            $translator->addMethodCall('addResource', ['php', $file, basename($file, '.php'), $this->domain]);
        }

        foreach (self::TEMPLATE_SCOPES as $directory => $scope) {
            foreach (glob($i18nDirectory.'/'.$directory.'/*/*.php') ?: [] as $file) {
                $templateName = basename(\dirname($file));
                $translator->addMethodCall('addResource', [
                    'php',
                    $file,
                    basename($file, '.php'),
                    $this->domain.'.'.$scope.'.'.$templateName,
                ]);
            }
        }
    }
}
