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

namespace StockAlert\Tests\Unit\PriceDrop;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StockAlert\PriceDrop\EffectivePriceResolver;

final class EffectivePriceResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float, float, bool}>
     */
    public static function drops(): iterable
    {
        yield 'a fifth off is above a 5% threshold' => [40.0, 32.0, 5.0, true];
        yield 'ten cents off 40 is under a 5% threshold' => [40.0, 39.90, 5.0, false];
        yield 'exactly the threshold counts' => [40.0, 38.0, 5.0, true];
        yield 'a hair under the threshold does not' => [40.0, 38.01, 5.0, false];
        yield 'a raise is never a drop' => [40.0, 45.0, 5.0, false];
        yield 'the same price is not a drop' => [40.0, 40.0, 0.0, false];
        yield 'a rounding difference is not a drop with a zero threshold' => [40.0, 39.9999999, 0.0, false];
        yield 'any real drop counts with a zero threshold' => [40.0, 39.99, 0.0, true];
        yield 'a negative threshold behaves like zero' => [40.0, 39.99, -3.0, true];
    }

    #[DataProvider('drops')]
    public function testASignificantDropIsAFallOfAtLeastTheThreshold(float $reference, float $current, float $threshold, bool $expected): void
    {
        self::assertSame($expected, EffectivePriceResolver::isSignificantDrop($reference, $current, $threshold));
    }
}
