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

namespace StockAlert\EventListeners;

use Propel\Runtime\ActiveQuery\Criteria;
use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\Model\RestockingAlert;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\MessageQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class StockAlertManager
 * @package StockAlert\EventListeners
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <jchanseaume@openstudio.fr>
 */
class StockAlertManager implements EventSubscriberInterface
{

    protected $parser;

    protected $mailer;

    public function __construct(ParserInterface $parser, MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            StockAlertEvents::STOCK_ALERT_SUBSCRIBE => ['subscribe', 128],
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => ['checkStock', 120],
            TheliaEvents::ORDER_UPDATE_STATUS => ['checkStockForAdmin', 128],
        ];
    }

    public function subscribe(StockAlertEvent $event)
    {
        $productSaleElementsId = $event->getProductSaleElementsId();
        $email = $event->getEmail();

        if (!isset($productSaleElementsId)) {
            throw new \Exception("missing param");
        }

        if (!isset($email)) {
            throw new \Exception("missing param");
        }

        // test if it already exists
        $subscribe = RestockingAlertQuery::create()
            ->filterByEmail($email)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->findOne()
        ;

        if (null === $subscribe) {

            $subscribe = new RestockingAlert();
            $subscribe
                ->setProductSaleElementsId($productSaleElementsId)
                ->setEmail($email)
                ->setLocale($event->getLocale())
                ->save()
            ;

        } else {

            throw new \Exception(
                Translator::getInstance()->trans(
                    "You have already subscribed to this product",
                    [],
                    StockAlert::DOMAIN_MESSAGE
                )
            );

        }

        $event->setRestockingAlert($subscribe);
    }


    public function checkStock(ProductSaleElementUpdateEvent $productSaleElementUpdateEvent)
    {
        if ($productSaleElementUpdateEvent->getQuantity() > 0) {

            $subscribers = RestockingAlertQuery::create()
                ->filterByProductSaleElementsId($productSaleElementUpdateEvent->getProductSaleElementId())
                ->find();

            if (null !== $subscribers) {
                foreach ($subscribers as $subscriber) {
                    try {
                        $this->sendEmail($subscriber);
                        $subscriber->delete();
                    } catch (\Exception $ex) {
                        ;
                    }
                }
            }
        }
    }

    public function sendEmail(RestockingAlert $subscriber)
    {
        $contactEmail = ConfigQuery::read('store_email');

        if ($contactEmail) {

            $message = MessageQuery::create()
                ->filterByName('stockalert_customer')
                ->findOne();

            if (null === $message) {
                throw new \Exception("Failed to load message 'stockalert_customer'.");
            }

            $pse = ProductSaleElementsQuery::create()->findPk($subscriber->getProductSaleElementsId());

            $this->parser->assign('locale', $subscriber->getLocale());
            $this->parser->assign('pse_id', $pse->getId());
            $this->parser->assign('product_id', $pse->getProductId());

            $message
                ->setLocale($subscriber->getLocale());

            $instance = \Swift_Message::newInstance()
                ->addTo($subscriber->getEmail(), ConfigQuery::read('store_name'))
                ->addFrom($contactEmail, ConfigQuery::read('store_name'));

            // Build subject and body
            $message->buildMessage($this->parser, $instance);

            $this->mailer->send($instance);

            Tlog::getInstance()->debug("Restocking Alert sent to customer " . $subscriber->getEmail());
        } else {
            Tlog::getInstance()->debug("Restocking Alert message no contact email Restocking Alert id",
                $subscriber->getId());
        }
    }

    public function checkStockForAdmin(OrderEvent $event)
    {
        $order = $event->getOrder();

        $config = StockAlert::getConfig();

        $pseIds = [];

        foreach ($order->getOrderProducts() as $orderProduct) {
            $pseIds[] = $orderProduct->getProductSaleElementsId();
        }

        if ($config['enabled']) {
            $threshold = $config['threshold'];

            $productIds = ProductQuery::create()
                ->useProductSaleElementsQuery()
                ->filterById($pseIds, Criteria::IN)
                ->filterByQuantity($threshold, Criteria::LESS_EQUAL)
                // exclude virtual product with weight at 0
                ->filterByWeight(0, Criteria::NOT_EQUAL)
                ->endUse()
                ->select('Id')
                ->find()
                ->toArray()
            ;

            if (!empty($productIds)) {
                $this->sendEmailForAdmin($config['emails'], $productIds);
            }
        }
    }

    public function sendEmailForAdmin($emails, $productIds)
    {
        $contactEmail = ConfigQuery::read('store_email');

        if ($contactEmail) {

            $message = MessageQuery::create()
                ->filterByName('stockalert_administrator')
                ->findOne();

            if (null === $message) {
                throw new \Exception("Failed to load message 'stockalert_administrator'.");
            }

            $locale = Lang::getDefaultLanguage()->getLocale();

            $this->parser->assign('locale', $locale);
            $this->parser->assign('products_id', $productIds);

            $message->setLocale($locale);

            $instance = \Swift_Message::newInstance();
            $instance->addFrom($contactEmail, ConfigQuery::read('store_name'));

            foreach ($emails as $email) {
                $instance->addTo($email);
            }

            // Build subject and body
            $message->buildMessage($this->parser, $instance);

            $this->mailer->send($instance);

            Tlog::getInstance()->debug("Stock Alert sent to administrator " . implode(', ', $emails));
        } else {
            Tlog::getInstance()->debug("Stock Alert sent to administrator " . implode(', ', $emails));
        }
    }
}
