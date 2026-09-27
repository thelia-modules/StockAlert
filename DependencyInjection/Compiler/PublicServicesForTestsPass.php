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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps the module services reachable from the test container.
 *
 * Symfony exposes a private service to the test container only as long as
 * something else in the compiled container still references it: a private
 * service nothing uses is removed, and one used exactly once is inlined into
 * its single consumer. Either way it disappears from the container the test
 * suite talks to, and the module tests then fail on an environment detail
 * rather than on the behaviour they exercise.
 *
 * The core does the same for its own services in
 * Thelia\Core\DependencyInjection\Compiler\TestPublicServicesPass, which only
 * covers ids starting with "Thelia\". Nothing changes for dev and prod.
 */
final readonly class PublicServicesForTestsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ('test' !== $container->getParameter('kernel.environment')) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'StockAlert\\')) {
                continue;
            }

            if ($definition->isPublic() || $definition->isAbstract() || $definition->hasErrors()) {
                continue;
            }

            $definition->setPublic(true);
        }
    }
}
