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

/*      Copyright (c) OpenStudio */
/*      email : dev@thelia.net */
/*      web : http://www.thelia.net */

/*      For the full copyright and license information, please view the LICENSE.txt */
/*      file that was distributed with this source code. */

namespace StockAlert;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Install\Database;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

/**
 * Class StockAlert.
 *
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class StockAlert extends BaseModule
{
    public const MESSAGE_DOMAIN = 'stockalert';
    public const CONFIG_ENABLED = 'stockalert_enabled';
    public const CONFIG_THRESHOLD = 'stockalert_threshold';
    public const CONFIG_EMAILS = 'stockalert_emails';
    public const CONFIG_NOTIFY = 'stockalert_notify';

    public const DEFAULT_ENABLED = '1';
    public const DEFAULT_THRESHOLD = '1';
    public const DEFAULT_EMAILS = '';
    public const DEFAULT_NOTIFY = '1';

    /** @var Translator */
    protected $translator;

    public static function getConfig()
    {
        $config = [
            'enabled' => ('1' == ConfigQuery::read(self::CONFIG_ENABLED, self::DEFAULT_ENABLED)),
            'threshold' => (int) ConfigQuery::read(self::CONFIG_THRESHOLD, self::DEFAULT_THRESHOLD),
            'emails' => explode(',', ConfigQuery::read(self::CONFIG_EMAILS, self::DEFAULT_EMAILS)),
            'notify' => ('1' == ConfigQuery::read(self::CONFIG_NOTIFY, self::DEFAULT_NOTIFY)),
        ];

        return $config;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(?ConnectionInterface $con = null): void
    {
        ConfigQuery::write(self::CONFIG_ENABLED, self::DEFAULT_ENABLED);
        ConfigQuery::write(self::CONFIG_THRESHOLD, self::DEFAULT_THRESHOLD);
        ConfigQuery::write(self::CONFIG_EMAILS, ConfigQuery::read('store_notification_emails'));
        ConfigQuery::write(self::CONFIG_NOTIFY, self::DEFAULT_NOTIFY);

        // create new message
        if (null === MessageQuery::create()->findOneByName('stockalert_customer')) {
            (new Message())
                ->setName('stockalert_customer')
                ->setHtmlTemplateFileName('alert-customer.html')
                ->setTextTemplateFileName('alert-customer.txt')
                ->setSecured(0)
                ->setLocale('en_US')
                ->setTitle('Stock Alert - Customer')
                ->setSubject('Product {$product_title} is available again')
                ->setLocale('fr_FR')
                ->setTitle('Alerte Stock - Client')
                ->setSubject('Le produit {$product_title} est à nouveau disponible')
                ->save();

            (new Message())
                ->setName('stockalert_administrator')
                ->setHtmlTemplateFileName('alert-administrator.html')
                ->setTextTemplateFileName('alert-administrator.txt')
                ->setSecured(0)
                ->setLocale('en_US')
                ->setTitle('Stock Alert - Administrator')
                ->setSubject('List of products nearly out of stock')
                ->setLocale('fr_FR')
                ->setTitle('Alerte Stock - Administrateur')
                ->setSubject('Liste des produits qui seront bientôt en rupture de stock')
                ->save();
        }

        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__.'/Config/thelia.sql']);
            self::setConfigValue('is_initialized', true);
        }
    }

    /**
     * @param bool $deleteModuleData
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        if (null !== $msg = MessageQuery::create()->findOneByName('stockalert_customer')) {
            $msg->delete();
        }
        if (null !== $msg = MessageQuery::create()->findOneByName('stockalert_administrator')) {
            $msg->delete();
        }

        ConfigQuery::create()
            ->filterByName([self::CONFIG_ENABLED, self::CONFIG_THRESHOLD, self::CONFIG_EMAILS, self::CONFIG_NOTIFY])
            ->delete()
        ;

        $database = new Database($con);
        $database->insertSql(null, [__DIR__.'/Config/destroy.sql']);
    }

    protected function trans(string $id, array $parameters = [], $locale = null)
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, self::MESSAGE_DOMAIN, $locale);
    }

    public function getHooks(): array
    {
        return [
            [
                'code' => 'product.stock-alert',
                'type' => TemplateDefinition::FRONT_OFFICE,
                'title' => [
                    'fr_FR' => 'Hook alertes stock',
                    'en_US' => 'Stock alert hook',
                ],
                'active' => true,
            ],
        ];
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
