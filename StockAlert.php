<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace StockAlert;

use Propel\Runtime\Connection\ConnectionInterface;
use StockAlert\Model\RestockingAlertQuery;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

/**
 * Class StockAlert
 * @package StockAlert
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class StockAlert extends BaseModule
{
    const MESSAGE_DOMAIN = "stockalert";

    const CONFIG_ENABLED = "stockalert_enabled";
    const CONFIG_THRESHOLD = "stockalert_threshold";
    const CONFIG_EMAILS = "stockalert_emails";

    const DEFAULT_ENABLED = "1";
    const DEFAULT_THRESHOLD = "1";
    const DEFAULT_EMAILS = "";

    /** @var Translator */
    protected $translator = null;

    public static function getConfig()
    {
        $config = [
            'enabled' => ("1" == ConfigQuery::read(self::CONFIG_ENABLED, self::DEFAULT_ENABLED)),
            'threshold' => intval(ConfigQuery::read(self::CONFIG_THRESHOLD, self::DEFAULT_THRESHOLD)),
            'emails' => explode(',', ConfigQuery::read(self::CONFIG_EMAILS, self::DEFAULT_EMAILS))
        ];

        return $config;
    }

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        ConfigQuery::write(self::CONFIG_ENABLED, self::DEFAULT_ENABLED);
        ConfigQuery::write(self::CONFIG_THRESHOLD, self::DEFAULT_THRESHOLD);
        ConfigQuery::write(self::CONFIG_EMAILS, ConfigQuery::read('store_notification_emails'));

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

        try {
            RestockingAlertQuery::create()->findOne();
        } catch (\Exception $e) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . '/Config/thelia.sql']);
        }
    }

    /**
     * @param ConnectionInterface|null $con
     * @param bool $deleteModuleData
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        if (null !== $msg = MessageQuery::create()->findOneByName('stockalert_customer')) {
            $msg->delete();
        }
        if (null !== $msg = MessageQuery::create()->findOneByName('stockalert_administrator')) {
            $msg->delete();
        }

        ConfigQuery::create()
            ->filterByName([ self::CONFIG_ENABLED, self::CONFIG_THRESHOLD, self::CONFIG_EMAILS ])
            ->delete()
        ;

        $database = new Database($con);
        $database->insertSql(null, [__DIR__ . '/Config/destroy.sql']);
    }

    protected function trans($id, array $parameters = [], $locale = null)
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, StockAlert::MESSAGE_DOMAIN, $locale);
    }

    public function getHooks()
    {
        return [
            [
                'code' => 'product.stock-alert',
                'type' => TemplateDefinition::FRONT_OFFICE,
                "title" => array(
                    "fr_FR" => "Hook alertes stock",
                    "en_US" => "Stock alert hook",
                ),
                "active" => true
            ]
        ];
    }
}
