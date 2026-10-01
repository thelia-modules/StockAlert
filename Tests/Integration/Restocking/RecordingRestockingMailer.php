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

namespace StockAlert\Tests\Integration\Restocking;

use Thelia\Mailer\MailerFactory;

/**
 * Keeps what the module asks the mailer to send, and fails on demand.
 */
final class RecordingRestockingMailer extends MailerFactory
{
    /** @var list<array{code: string, to: array<string, string>, parameters: array<string, mixed>, locale: ?string}> */
    public array $sent = [];

    public bool $failing = false;

    public function sendEmailMessageOrFail(
        string $messageCode,
        array $from,
        array $to,
        array $messageParameters = [],
        ?string $locale = null,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
    ): void {
        if ($this->failing) {
            throw new \RuntimeException('The mail server is down.');
        }

        $this->sent[] = ['code' => $messageCode, 'to' => $to, 'parameters' => $messageParameters, 'locale' => $locale];
    }

    public function sendEmailMessage(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): void
    {
        try {
            $this->sendEmailMessageOrFail($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc, $replyTo);
        } catch (\RuntimeException) {
            // As the real one: a message that does not leave is logged, never thrown.
        }
    }
}
