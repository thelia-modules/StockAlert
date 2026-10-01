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

use StockAlert\Service\RestockingMailer;
use StockAlert\StockAlert;
use Thelia\Model\Lang;
use Thelia\Tools\URL;

final class RestockingMailerTest extends RestockingTestCase
{
    private RestockingMailer $restockingMailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restockingMailer = new RestockingMailer($this->mailer, $this->getService(URL::class));
    }

    public function testTheBackInStockMessageCarriesTheVariablesOfItsTemplate(): void
    {
        $productSaleElements = $this->productOutOfStock('Casque jet')->getDefaultSaleElements();
        $email = $this->uniqueEmail();

        $this->restockingMailer->sendBackInStock($email, 'fr_FR', $productSaleElements->getId());

        self::assertCount(1, $this->mailer->sent);
        $sent = $this->mailer->sent[0];
        self::assertSame(StockAlert::MESSAGE_CUSTOMER, $sent['code']);
        self::assertSame([$email => 'The shop'], $sent['to']);
        self::assertSame('fr_FR', $sent['locale']);
        self::assertSame('fr_FR', $sent['parameters']['locale']);
        self::assertSame($productSaleElements->getProductId(), $sent['parameters']['product_id']);
        self::assertSame($productSaleElements->getId(), $sent['parameters']['pse_id']);
        self::assertSame('Casque jet', $sent['parameters']['product_title']);
        self::assertStringStartsWith('http', $sent['parameters']['product_url']);
        self::assertSame('', $sent['parameters']['combination'], 'a product without attribute');
    }

    public function testTheAcknowledgementIsTheOtherMessageOfTheModule(): void
    {
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();

        $this->restockingMailer->sendSubscribed($this->uniqueEmail(), 'en_US', $productSaleElements->getId());

        self::assertSame(StockAlert::MESSAGE_SUBSCRIBED, $this->mailer->sent[0]['code']);
    }

    public function testAProductNotTitledInTheLanguageIsNamedInTheDefaultOne(): void
    {
        $productSaleElements = $this->productOutOfStock('Only titled in the default language')->getDefaultSaleElements();
        $unknownLocale = 'xx_XX' === Lang::getDefaultLanguage()->getLocale() ? 'yy_YY' : 'xx_XX';

        $this->restockingMailer->sendBackInStock($this->uniqueEmail(), $unknownLocale, $productSaleElements->getId());

        self::assertSame('Only titled in the default language', $this->mailer->sent[0]['parameters']['product_title']);
    }

    public function testAMessageThatDoesNotLeaveIsAFailureTheCallerSees(): void
    {
        $productSaleElements = $this->productOutOfStock()->getDefaultSaleElements();
        $this->mailer->failing = true;

        $this->expectExceptionMessage('The mail server is down.');
        $this->restockingMailer->sendBackInStock($this->uniqueEmail(), 'fr_FR', $productSaleElements->getId());
    }

    public function testAnUnknownSaleElementIsAFailureTheCallerSees(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->restockingMailer->sendBackInStock($this->uniqueEmail(), 'fr_FR', 999999999);
    }
}
