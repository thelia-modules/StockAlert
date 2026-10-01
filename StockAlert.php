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
use StockAlert\DependencyInjection\Compiler\PublicServicesForTestsPass;
use StockAlert\DependencyInjection\Compiler\RegisterModuleTranslationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Install\Database;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
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
    public const CONFIG_NEWSLETTER = 'stockalert_newsletter';
    public const CONFIG_CONFIRMATION = 'stockalert_confirmation';

    public const CONFIG_PRICE_DROP_ENABLED = 'stockalert_price_drop_enabled';
    public const CONFIG_PRICE_DROP_THRESHOLD_PERCENT = 'stockalert_price_drop_threshold_percent';
    public const CONFIG_PRICE_DROP_EXPIRATION_DAYS = 'stockalert_price_drop_expiration_days';
    public const CONFIG_PRICE_DROP_MAX_PER_EMAIL = 'stockalert_price_drop_max_per_email';
    public const CONFIG_PRICE_DROP_BATCH_SIZE = 'stockalert_price_drop_batch_size';

    public const MESSAGE_PRICE_DROP = 'stockalert_price_drop';
    public const MESSAGE_CUSTOMER = 'stockalert_customer';
    public const MESSAGE_ADMINISTRATOR = 'stockalert_administrator';
    public const MESSAGE_SUBSCRIBED = 'stockalert_subscribed';

    public const DEFAULT_PRICE_DROP_ENABLED = '0';
    public const DEFAULT_PRICE_DROP_THRESHOLD_PERCENT = '5';
    public const DEFAULT_PRICE_DROP_EXPIRATION_DAYS = '180';
    public const DEFAULT_PRICE_DROP_MAX_PER_EMAIL = '20';
    public const DEFAULT_PRICE_DROP_BATCH_SIZE = '200';

    public const DEFAULT_ENABLED = '1';
    public const DEFAULT_THRESHOLD = '1';
    public const DEFAULT_EMAILS = '';
    public const DEFAULT_NOTIFY = '1';
    public const DEFAULT_NEWSLETTER = '0';
    public const DEFAULT_CONFIRMATION = '0';

    /** @var Translator */
    protected $translator;

    public static function getConfig()
    {
        $config = [
            'enabled' => ('1' == ConfigQuery::read(self::CONFIG_ENABLED, self::DEFAULT_ENABLED)),
            'threshold' => (int) ConfigQuery::read(self::CONFIG_THRESHOLD, self::DEFAULT_THRESHOLD),
            'emails' => explode(',', ConfigQuery::read(self::CONFIG_EMAILS, self::DEFAULT_EMAILS)),
            'notify' => ('1' == ConfigQuery::read(self::CONFIG_NOTIFY, self::DEFAULT_NOTIFY)),
            'newsletter' => ('1' == ConfigQuery::read(self::CONFIG_NEWSLETTER, self::DEFAULT_NEWSLETTER)),
            'confirmation' => ('1' == ConfigQuery::read(self::CONFIG_CONFIRMATION, self::DEFAULT_CONFIRMATION)),
        ];

        return $config;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(?ConnectionInterface $con = null): void
    {
        $this->ensureSettings();
        $this->ensureMessages($con);

        $this->ensurePriceDropSetup($con);

        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__.'/Config/thelia.sql']);
            self::setConfigValue('is_initialized', true);
        }
    }

    /**
     * Runs every Config/update/<version>.sql newer than the installed version,
     * then brings the price drop configuration up to date.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $updateDirectory = __DIR__.DS.'Config'.DS.'update';

        if (is_dir($updateDirectory)) {
            $finder = Finder::create()
                ->name('*.sql')
                ->depth(0)
                ->sortByName()
                ->in($updateDirectory);

            $database = new Database($con);

            foreach ($finder as $file) {
                if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                    $database->insertSql(null, [$file->getPathname()]);
                }
            }
        }

        $this->ensureSettings();
        $this->ensureMessages($con);
        $this->ensurePriceDropSetup($con);
    }

    /**
     * Writes the default of each setting the shop has no value for, and nothing else: activating the
     * module again, or updating it, never takes back what the merchant chose (or what the shop was
     * set up with before the module was activated).
     */
    private function ensureSettings(): void
    {
        $defaults = [
            self::CONFIG_ENABLED => self::DEFAULT_ENABLED,
            self::CONFIG_THRESHOLD => self::DEFAULT_THRESHOLD,
            self::CONFIG_EMAILS => (string) ConfigQuery::read('store_notification_emails'),
            self::CONFIG_NOTIFY => self::DEFAULT_NOTIFY,
            self::CONFIG_NEWSLETTER => self::DEFAULT_NEWSLETTER,
            self::CONFIG_CONFIRMATION => self::DEFAULT_CONFIRMATION,
        ];

        foreach ($defaults as $name => $value) {
            if (null === ConfigQuery::read($name)) {
                ConfigQuery::write($name, $value);
            }
        }
    }

    /**
     * Creates the messages of the module, and gives the ones that already exist the languages of the shop
     * they lack (an install of 3.1 has English and French only): a title or a subject that is there is kept.
     * The text of a message is its template, `templates/email/default/<name>.html.twig` and `.txt.twig`.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    private function ensureMessages(?ConnectionInterface $con = null): void
    {
        $messages = [
            self::MESSAGE_CUSTOMER => [
                'alert-customer.html', 'alert-customer.txt',
                ['Stock Alert - Customer', 'Alerte Stock - Client', 'Lagerbenachrichtigung - Kunde'],
                ['Product {{ product_title }} is available again', 'Le produit {{ product_title }} est à nouveau disponible', 'Der Artikel {{ product_title }} ist wieder verfügbar'],
            ],
            self::MESSAGE_ADMINISTRATOR => [
                'alert-administrator.html', 'alert-administrator.txt',
                ['Stock Alert - Administrator', 'Alerte Stock - Administrateur', 'Lagerbenachrichtigung - Administrator'],
                ['List of products nearly out of stock', 'Liste des produits qui seront bientôt en rupture de stock', 'Artikel, die bald nicht mehr auf Lager sind'],
            ],
            self::MESSAGE_SUBSCRIBED => [
                'alert-subscribed.html', 'alert-subscribed.txt',
                ['Stock Alert - Subscription', 'Alerte Stock - Inscription', 'Lagerbenachrichtigung - Vormerkung'],
                ['Your alert for {{ product_title }}', 'Votre alerte pour le produit {{ product_title }}', 'Ihre Benachrichtigung für {{ product_title }}'],
            ],
        ];

        foreach ($messages as $name => [$htmlTemplate, $textTemplate, $titles, $subjects]) {
            $message = MessageQuery::create()->findOneByName($name) ?? (new Message())
                ->setName($name)
                ->setHtmlTemplateFileName($htmlTemplate)
                ->setTextTemplateFileName($textTemplate)
                ->setSecured(0);

            foreach (LangQuery::create()->find($con) as $lang) {
                $locale = $lang->getLocale();
                $index = match (substr($locale, 0, 2)) {
                    'fr' => 1,
                    'de' => 2,
                    default => 0,
                };

                $message->setLocale($locale);

                if ('' === trim((string) $message->getTitle())) {
                    $message->setTitle($titles[$index]);
                }

                if ('' === trim((string) $message->getSubject())) {
                    $message->setSubject($subjects[$index]);
                }
            }

            $message->save($con);
        }
    }

    /**
     * Creates what the price drop alert needs and nothing else: a shop that
     * already has a value keeps it, which is what makes this safe to call from
     * both postActivation() and update().
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    private function ensurePriceDropSetup(?ConnectionInterface $con = null): void
    {
        $defaults = [
            self::CONFIG_PRICE_DROP_ENABLED => self::DEFAULT_PRICE_DROP_ENABLED,
            self::CONFIG_PRICE_DROP_THRESHOLD_PERCENT => self::DEFAULT_PRICE_DROP_THRESHOLD_PERCENT,
            self::CONFIG_PRICE_DROP_EXPIRATION_DAYS => self::DEFAULT_PRICE_DROP_EXPIRATION_DAYS,
            self::CONFIG_PRICE_DROP_MAX_PER_EMAIL => self::DEFAULT_PRICE_DROP_MAX_PER_EMAIL,
            self::CONFIG_PRICE_DROP_BATCH_SIZE => self::DEFAULT_PRICE_DROP_BATCH_SIZE,
        ];

        foreach ($defaults as $name => $value) {
            if (null === ConfigQuery::read($name)) {
                ConfigQuery::write($name, $value);
            }
        }

        if (null !== MessageQuery::create()->findOneByName(self::MESSAGE_PRICE_DROP)) {
            return;
        }

        $message = (new Message())
            ->setName(self::MESSAGE_PRICE_DROP)
            ->setHtmlTemplateFileName('price-drop-customer.html')
            ->setHtmlLayoutFileName('')
            ->setTextTemplateFileName('price-drop-customer.txt')
            ->setTextLayoutFileName('')
            ->setSecured(0);

        foreach (LangQuery::create()->find($con) as $lang) {
            $locale = $lang->getLocale();
            [$title, $subject] = match (substr($locale, 0, 2)) {
                'fr' => ['Alerte Baisse de Prix - Client', 'Le prix de {{ product_title }} a baissé'],
                'de' => ['Preisbenachrichtigung - Kunde', 'Der Preis von {{ product_title }} ist gesunken'],
                default => ['Price Drop Alert - Customer', 'The price of {{ product_title }} has dropped'],
            };

            $message
                ->setLocale($locale)
                ->setTitle($title)
                ->setSubject($subject);
        }

        $message->save($con);
    }

    /**
     * @param bool $deleteModuleData
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        foreach ([self::MESSAGE_CUSTOMER, self::MESSAGE_ADMINISTRATOR, self::MESSAGE_SUBSCRIBED, self::MESSAGE_PRICE_DROP] as $name) {
            if (null !== $msg = MessageQuery::create()->findOneByName($name)) {
                $msg->delete();
            }
        }

        ConfigQuery::create()
            ->filterByName([
                self::CONFIG_ENABLED,
                self::CONFIG_THRESHOLD,
                self::CONFIG_EMAILS,
                self::CONFIG_NOTIFY,
                self::CONFIG_NEWSLETTER,
                self::CONFIG_CONFIRMATION,
                self::CONFIG_PRICE_DROP_ENABLED,
                self::CONFIG_PRICE_DROP_THRESHOLD_PERCENT,
                self::CONFIG_PRICE_DROP_EXPIRATION_DAYS,
                self::CONFIG_PRICE_DROP_MAX_PER_EMAIL,
                self::CONFIG_PRICE_DROP_BATCH_SIZE,
            ])
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

    /**
     * TYPE_BEFORE_REMOVING is what makes the pass safe: autowiring has already
     * run, so a definition the container cannot build carries its error and is
     * left private, then removed, instead of being forced into the compiled
     * container, where it would turn every boot into a fatal.
     */
    public static function getCompilers(): array
    {
        return [
            [new RegisterModuleTranslationsPass(__DIR__, self::MESSAGE_DOMAIN), PassConfig::TYPE_BEFORE_OPTIMIZATION],
            [new PublicServicesForTestsPass(), PassConfig::TYPE_BEFORE_REMOVING],
        ];
    }

    /**
     * The price drop form mails an address the visitor typed, so it gets its own
     * budgets rather than sharing the core's password-reset ones: a visitor who
     * follows a few products must still be able to ask for a new password.
     */
    public static function loadConfiguration(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'rate_limiter' => [
                'stockalert_price_drop_per_address' => [
                    'policy' => 'sliding_window',
                    'limit' => 5,
                    'interval' => '1 hour',
                ],
                'stockalert_price_drop_per_client' => [
                    'policy' => 'sliding_window',
                    'limit' => 30,
                    'interval' => '1 hour',
                ],
            ],
        ]);
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Tests/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
