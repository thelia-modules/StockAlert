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

use StockAlert\StockAlert;
use Thelia\Mailer\MailerFactory;

/**
 * Renders the restocking messages through the real mailer, in each language the module is translated into:
 * a template that does not compile, or a missing translation, shows up here and not in a shopper's mailbox.
 */
final class RestockingEmailRenderingTest extends RestockingTestCase
{
    private const PARAMETERS = [
        'product_id' => 1,
        'pse_id' => 1,
        'product_title' => 'Casque <jet>',
        'product_url' => 'https://shop.example.com/casque-jet.html',
        'combination' => 'M / Noir',
    ];

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function customerMessages(): iterable
    {
        yield 'French' => ['fr_FR', 'est à nouveau disponible', 'Le commander', 'Bonjour'];
        yield 'German' => ['de_DE', 'ist wieder verfügbar', 'Jetzt bestellen', 'Guten Tag'];
        yield 'English' => ['en_US', 'is available again', 'Order it now', 'Hello'];
    }

    /**
     * @dataProvider customerMessages
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('customerMessages')]
    public function testTheBackInStockMessageIsInTheLanguageOfTheSubscriberWithTheLinkToTheProduct(string $locale, string $subject, string $call, string $greeting): void
    {
        $email = $this->render(StockAlert::MESSAGE_CUSTOMER, $locale);
        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();

        self::assertStringContainsString($subject, (string) $email->getSubject());
        self::assertStringContainsString($greeting, $html);
        self::assertStringContainsString($call, $html);
        self::assertStringContainsString('href="https://shop.example.com/casque-jet.html"', $html, 'the link of the product page, which Thelia 2 left empty');
        self::assertStringContainsString('Casque &lt;jet&gt; (M / Noir)', $html, 'the title is escaped, the combination shown');
        self::assertStringContainsString('https://shop.example.com/casque-jet.html', $text);
        self::assertStringNotContainsString('<a ', $text);
    }

    public function testTheAcknowledgementNamesTheProductAndLinksToIt(): void
    {
        $email = $this->render(StockAlert::MESSAGE_SUBSCRIBED, 'fr_FR');
        $html = (string) $email->getHtmlBody();

        self::assertStringContainsString('Votre alerte pour le produit', (string) $email->getSubject());
        self::assertStringContainsString('<a href="https://shop.example.com/casque-jet.html">Casque &lt;jet&gt;</a> (M / Noir)', $html);
        self::assertStringContainsString('Nous vous avertirons', $html);
        self::assertStringContainsString('Nous vous avertirons', (string) $email->getTextBody());
    }

    public function testTheAcknowledgementIsInGermanToo(): void
    {
        $email = $this->render(StockAlert::MESSAGE_SUBSCRIBED, 'de_DE');

        self::assertStringContainsString('Ihre Benachrichtigung für', (string) $email->getSubject());
        self::assertStringContainsString('wurde vorgemerkt', (string) $email->getHtmlBody());
    }

    public function testTheMessageToTheAdministratorListsTheProductsWithTheirPages(): void
    {
        $email = $this->getService(MailerFactory::class)->createEmailMessage(
            StockAlert::MESSAGE_ADMINISTRATOR,
            ['shop@example.com' => 'The shop'],
            ['admin@example.com' => 'admin@example.com'],
            [
                'locale' => 'fr_FR',
                'products_id' => [4],
                'products' => [[
                    'id' => 4,
                    'ref' => 'JET-1',
                    'title' => 'Casque <jet>',
                    'url' => 'https://shop.example.com/casque-jet.html',
                    'admin_url' => 'https://shop.example.com/admin/products/update?product_id=4',
                ]],
            ],
            'fr_FR',
        );

        $html = (string) $email->getHtmlBody();

        self::assertStringContainsString('rupture de stock', (string) $email->getSubject());
        self::assertStringContainsString('4: JET-1 - Casque &lt;jet&gt;', $html);
        self::assertStringContainsString('href="https://shop.example.com/admin/products/update?product_id=4"', $html);
        self::assertStringContainsString('4: JET-1 - Casque <jet>', (string) $email->getTextBody());
    }

    private function render(string $message, string $locale): \Symfony\Component\Mime\Email
    {
        return $this->getService(MailerFactory::class)->createEmailMessage(
            $message,
            ['shop@example.com' => 'The shop'],
            ['shopper@example.com' => 'shopper@example.com'],
            ['locale' => $locale, ...self::PARAMETERS],
            $locale,
        );
    }
}
