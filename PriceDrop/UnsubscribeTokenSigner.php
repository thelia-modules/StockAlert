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

namespace StockAlert\PriceDrop;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Names one subscription in an unsubscribe link without a token column: the id
 * and an expiry, signed with the application secret. A link that does not
 * verify says nothing about whether the subscription exists.
 */
final readonly class UnsubscribeTokenSigner
{
    private const SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $applicationSecret,
    ) {
    }

    public function sign(int $priceDropAlertId, \DateTimeInterface $expiresAt): string
    {
        $payload = $priceDropAlertId.'.'.$expiresAt->getTimestamp();

        return $this->encode($payload.'.'.$this->signature($payload));
    }

    /**
     * @return int|null the subscription id, or null when the token is forged, altered or expired
     */
    public function verify(string $token, ?\DateTimeInterface $now = null): ?int
    {
        $decoded = $this->decode($token);

        if (null === $decoded) {
            return null;
        }

        $parts = explode('.', $decoded);

        if (3 !== \count($parts)) {
            return null;
        }

        [$priceDropAlertId, $expiresAt, $signature] = $parts;

        if (!ctype_digit($priceDropAlertId) || !ctype_digit($expiresAt)) {
            return null;
        }

        if (!hash_equals($this->signature($priceDropAlertId.'.'.$expiresAt), $signature)) {
            return null;
        }

        if ((int) $expiresAt < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
            return null;
        }

        return (int) $priceDropAlertId;
    }

    private function signature(string $payload): string
    {
        return hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $this->applicationSecret);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $token): ?string
    {
        if ('' === $token || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            return null;
        }

        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
