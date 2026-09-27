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

use PHPUnit\Framework\TestCase;
use StockAlert\PriceDrop\UnsubscribeTokenSigner;

final class UnsubscribeTokenSignerTest extends TestCase
{
    private UnsubscribeTokenSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new UnsubscribeTokenSigner('a-secret-nobody-guesses');
    }

    public function testATokenNamesTheSubscriptionItWasIssuedFor(): void
    {
        $token = $this->signer->sign(42, new \DateTimeImmutable('+1 day'));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
        self::assertSame(42, $this->signer->verify($token));
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $token = $this->signer->sign(42, new \DateTimeImmutable('-1 second'));

        self::assertNull($this->signer->verify($token));
    }

    public function testATokenSignedWithAnotherSecretIsRefused(): void
    {
        $token = (new UnsubscribeTokenSigner('another-secret'))->sign(42, new \DateTimeImmutable('+1 day'));

        self::assertNull($this->signer->verify($token));
    }

    public function testChangingTheSubscriptionIdBreaksTheSignature(): void
    {
        $token = $this->signer->sign(42, new \DateTimeImmutable('+1 day'));
        $payload = base64_decode(strtr($token, '-_', '+/'), true);
        self::assertIsString($payload);

        $forged = rtrim(strtr(base64_encode(preg_replace('/^42\./', '43.', $payload)), '+/', '-_'), '=');

        self::assertNull($this->signer->verify($forged));
    }

    public function testGarbageIsRefusedWithoutAnError(): void
    {
        self::assertNull($this->signer->verify(''));
        self::assertNull($this->signer->verify('not base64 at all!'));
        self::assertNull($this->signer->verify(rtrim(base64_encode('one.two'), '=')));
    }
}
