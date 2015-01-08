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

use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\Model\RestockingAlert;
use StockAlert\Model\RestockingAlertQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
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
        return array(
            StockAlertEvents::STOCK_ALERT_SUBSCRIBE =>array(
                'subscribe' , 128
            ),
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => array(
                'checkStock', 120
            )
        );

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

        $subscribe = new RestockingAlert();
        $subscribe
            ->setProductSaleElementsId($productSaleElementsId)
            ->setEmail($email)
            ->save()
        ;

        $event->setRestockingAlert($subscribe);
    }


    public function checkStock(ProductSaleElementUpdateEvent $productSaleElementUpdateEvent)
    {
        if ($productSaleElementUpdateEvent->getQuantity() > 0) {

            $subscribers = RestockingAlertQuery::create()
                ->filterByProductSaleElementsId($productSaleElementUpdateEvent->getProductSaleElementId())
                ->find()
            ;

            if (null !== $subscribers) {

                foreach ($subscribers as $subscriber) {

                    $this->sendEmail($subscriber);
                }

                $subscribers->delete();
            }

        }

    }

    public function sendEmail(RestockingAlert $subscriber)
    {
        $contactEmail = ConfigQuery::read('store_email');

        if ($contactEmail) {

            $message = MessageQuery::create()
                ->filterByName('stockalert_customer')
                ->findOne()
            ;

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
                ->addFrom($contactEmail, ConfigQuery::read('store_name'))
            ;

            // Build subject and body
            $message->buildMessage($this->parser, $instance);

            $this->mailer->send($instance);

            Tlog::getInstance()->debug("Restocking Alert sent to customer ".$subscriber->getEmail());
        }
        else {
            Tlog::getInstance()->debug("Restocking Alert message no contact email Restocking Alert id", $subscriber->getId());
        }
    }
}
