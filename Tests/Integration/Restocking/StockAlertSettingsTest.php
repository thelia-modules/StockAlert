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

use Propel\Runtime\Propel;
use StockAlert\StockAlert;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\MessageTableMap;
use Thelia\Model\MessageQuery;

/**
 * What activating and updating the module write: settings only where the shop has none, messages in every
 * language of the shop.
 */
final class StockAlertSettingsTest extends RestockingTestCase
{
    public function testActivatingAgainKeepsWhatTheMerchantChose(): void
    {
        $this->writeConfig(StockAlert::CONFIG_ENABLED, '0');
        $this->writeConfig(StockAlert::CONFIG_THRESHOLD, '7');
        $this->writeConfig(StockAlert::CONFIG_EMAILS, 'stock@example.com');
        $this->writeConfig(StockAlert::CONFIG_NEWSLETTER, '1');

        (new StockAlert())->postActivation();

        $config = StockAlert::getConfig();
        self::assertFalse($config['enabled'], 'the alert to the administrator, off, stays off');
        self::assertSame(7, $config['threshold']);
        self::assertSame(['stock@example.com'], $config['emails']);
        self::assertTrue($config['newsletter']);
    }

    public function testAnActivationOnAShopWithoutSettingsWritesTheDefaults(): void
    {
        foreach ([StockAlert::CONFIG_ENABLED, StockAlert::CONFIG_NEWSLETTER, StockAlert::CONFIG_CONFIRMATION, StockAlert::CONFIG_NOTIFY, StockAlert::CONFIG_THRESHOLD] as $name) {
            $this->removeConfig($name);
        }

        (new StockAlert())->postActivation();

        $config = StockAlert::getConfig();
        self::assertTrue($config['enabled']);
        self::assertTrue($config['notify']);
        self::assertSame(1, $config['threshold']);
        self::assertFalse($config['newsletter'], 'no newsletter checkbox unless the shop asks for it');
        self::assertFalse($config['confirmation']);
    }

    public function testASettingWrittenBeforeTheActivationIsTheOneTheShopStartsWith(): void
    {
        $this->removeConfig(StockAlert::CONFIG_ENABLED);
        $this->writeConfig(StockAlert::CONFIG_ENABLED, '0');

        (new StockAlert())->postActivation();

        self::assertFalse(StockAlert::getConfig()['enabled']);
    }

    public function testEveryMessageHasATitleAndASubjectInEveryLanguageOfTheShop(): void
    {
        (new StockAlert())->postActivation();

        foreach ([StockAlert::MESSAGE_CUSTOMER, StockAlert::MESSAGE_ADMINISTRATOR, StockAlert::MESSAGE_SUBSCRIBED] as $name) {
            $message = MessageQuery::create()->findOneByName($name);
            self::assertNotNull($message, $name);

            foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
                $message->setLocale($locale);
                self::assertNotSame('', trim((string) $message->getTitle()), $name.' '.$locale);
                self::assertNotSame('', trim((string) $message->getSubject()), $name.' '.$locale);
            }
        }
    }

    public function testAnUpdateGivesAnOldInstallTheMessageAndTheLanguagesItLacks(): void
    {
        $message = MessageQuery::create()->findOneByName(StockAlert::MESSAGE_CUSTOMER);
        $message->setLocale('de_DE')->setTitle('')->setSubject('')->save();
        $message->setLocale('fr_FR')->setSubject('Sujet choisi par le marchand')->save();
        MessageQuery::create()->findOneByName(StockAlert::MESSAGE_SUBSCRIBED)?->delete();

        (new StockAlert())->update('3.1.0', '3.2.0', Propel::getConnection(MessageTableMap::DATABASE_NAME));

        $message = MessageQuery::create()->findOneByName(StockAlert::MESSAGE_CUSTOMER);
        self::assertSame('Der Artikel {{ product_title }} ist wieder verfügbar', $message->setLocale('de_DE')->getSubject());
        self::assertSame('Sujet choisi par le marchand', $message->setLocale('fr_FR')->getSubject(), 'what is there is kept');
        self::assertNotNull(MessageQuery::create()->findOneByName(StockAlert::MESSAGE_SUBSCRIBED), 'the new message');
    }

    private function removeConfig(string $name): void
    {
        $this->writeConfig($name, '');
        ConfigQuery::create()->filterByName($name)->delete();
    }
}
