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

namespace StockAlert\Tests\Integration\PriceDrop;

use StockAlert\StockAlert;
use Thelia\Mailer\MailerFactory;

/**
 * Renders the module's own email templates through the real mailer: a template
 * that does not compile, or a missing translation domain, shows up here and not
 * in a shopper's mailbox.
 */
final class PriceDropEmailRenderingTest extends PriceDropTestCase
{
    public function testTheEmailCarriesBothPricesAndTheLinkInTheSubscriberLanguage(): void
    {
        $email = $this->getService(MailerFactory::class)->createEmailMessage(
            StockAlert::MESSAGE_PRICE_DROP,
            ['shop@example.com' => 'The shop'],
            ['shopper@example.com' => 'shopper@example.com'],
            [
                'locale' => 'fr_FR',
                'product_id' => 1,
                'pse_id' => 1,
                'product_title' => 'Cafetière <italienne>',
                'product_url' => 'https://shop.example.com/cafetiere-italienne.html',
                'currency_id' => $this->currency->getId(),
                'currency_code' => $this->currency->getCode(),
                'currency_symbol' => $this->currency->getSymbol(),
                'old_untaxed_price' => 40.0,
                'new_untaxed_price' => 32.0,
                'old_price' => 48.0,
                'new_price' => 38.4,
            ],
            'fr_FR',
        );

        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();

        // The core renders every message subject through Twig with autoescape on, so a
        // title carrying markup characters reaches the subject escaped. Not this module's call.
        self::assertStringStartsWith('Le prix de Cafetière', (string) $email->getSubject());
        self::assertStringEndsWith('a baissé', (string) $email->getSubject());
        self::assertStringContainsString('Cafetière &lt;italienne&gt;', $html, 'the product title is escaped in the HTML body');
        self::assertStringContainsString('48,00', $html);
        self::assertStringContainsString('38,40', $html);
        self::assertStringContainsString('href="https://shop.example.com/cafetiere-italienne.html"', $html);
        self::assertStringContainsString('Prix lors de votre inscription', $html);
        self::assertStringContainsString('https://shop.example.com/cafetiere-italienne.html', $text);
        self::assertStringContainsString('38,40', $text);
        self::assertStringNotContainsString('<a ', $text);
    }
}
