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
use StockAlert\Event\ProductSaleElementAvailabilityEvent;
use StockAlert\Event\StockAlertEvent;
use StockAlert\Event\StockAlertEvents;
use StockAlert\Model\RestockingAlert;
use StockAlert\Model\RestockingAlertQuery;
use StockAlert\Model\StockProductAlert;
use StockAlert\Model\StockProductAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class StockAlertManager
 * @package StockAlert\EventListeners
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertManager implements EventSubscriberInterface
{
    protected $mailer;

    public function __construct(MailerFactory $mailer)
    {
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
        $subscribeToNewsLetter = $event->getSubscribeToNewsLetter();


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
            ->findOne();

        if (null === $subscribe) {
            $subscribe = new RestockingAlert();
            $subscribe
                ->setProductSaleElementsId($productSaleElementsId)
                ->setEmail($email)
                ->setLocale($event->getLocale())
                ->save();
        }

        if ($subscribeToNewsLetter) {
            $this->subscribeNewsletter($email, $event);
        }


        $event->setRestockingAlert($subscribe);
    }

    protected function subscribeNewsletter($email, StockAlertEvent $event)
    {
        $customer = NewsletterQuery::create()->findOneByEmail($email);

        if (!$customer) {

            $newsletter = new NewsletterEvent($email, "fr_FR");
            $event->getDispatcher()->dispatch(TheliaEvents::NEWSLETTER_SUBSCRIBE, $newsletter);

        }
    }


    public function checkStock(ProductSaleElementUpdateEvent $productSaleElementUpdateEvent)
    {
        if ($productSaleElementUpdateEvent->getQuantity() > 0) {
            // add extra checking
            $pse = ProductSaleElementsQuery::create()->findPk(
                $productSaleElementUpdateEvent->getProductSaleElementId()
            );
            $availabilityEvent = new ProductSaleElementAvailabilityEvent(
                $pse
            );

            $productSaleElementUpdateEvent->getDispatcher()->dispatch(
                StockAlertEvents::STOCK_ALERT_CHECK_AVAILABILITY,
                $availabilityEvent
            );

            if ($availabilityEvent->isAvailable()) {
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
    }

    /**
     * @param RestockingAlert $subscriber
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function sendEmail(RestockingAlert $subscriber)
    {
        $contactEmail = ConfigQuery::read('store_email');

        if ($contactEmail) {
            $pse = ProductSaleElementsQuery::create()->findPk($subscriber->getProductSaleElementsId());

            $this->mailer->sendEmailMessage(
                'stockalert_customer',
                [$contactEmail => ConfigQuery::read('store_name')],
                [$subscriber->getEmail() => ConfigQuery::read('store_name')],
                [
                    'locale' => $subscriber->getLocale(),
                    'pse_id' => $pse->getId(),
                    'product_id' => $pse->getProductId(),
                    'product_title' => $pse->getProduct()->setLocale($subscriber->getLocale())->getTitle()
                ],
                $subscriber->getLocale()
            );

            Tlog::getInstance()->debug("Restocking Alert sent to customer " . $subscriber->getEmail());
        } else {
            Tlog::getInstance()->debug(
                "Restocking Alert: no contact email is defined !"
            );
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
                ->toArray();

            if (!empty($productIds)) {
                foreach ($productIds as $productId) {

                    if (!StockProductAlertQuery::create()->findOneByProductId($productId)){
                        $stockPseAlert = new StockProductAlert();
                        $stockPseAlert->setProductId($productId);
                        $stockPseAlert->save();
                    }
                }
            }
        }
    }
}
