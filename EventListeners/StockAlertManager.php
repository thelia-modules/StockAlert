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
use StockAlert\Service\RestockingMailer;
use StockAlert\StockAlert;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

/**
 * Class StockAlertManager
 * @package StockAlert\EventListeners
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertManager implements EventSubscriberInterface
{
    protected $mailer;

    protected $dispatcher;

    private readonly RestockingMailer $restockingMailer;

    public function __construct(
        MailerFactory $mailer,
        EventDispatcherInterface $dispatcher,
        ?RestockingMailer $restockingMailer = null,
    ) {
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
        // Optional so that a subclass or a service definition written for 3.1 keeps working.
        $this->restockingMailer = $restockingMailer ?? new RestockingMailer($mailer, URL::getInstance());
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents(): array
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

        $isNew = null === $subscribe;

        if ($isNew) {
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

        if ($isNew && StockAlert::getConfig()['confirmation']) {
            try {
                $this->restockingMailer->sendSubscribed($email, (string) $event->getLocale(), (int) $productSaleElementsId);
            } catch (\Throwable $exception) {
                // The subscription stands: only the acknowledgement did not leave. The text of a mailer's exception may
                // quote the address: the class and the sale element only.
                Tlog::getInstance()->error(\sprintf('Stock alert: the acknowledgement for sale element %d was not sent (%s)', $productSaleElementsId, $exception::class));
            }
        }


        $event->setRestockingAlert($subscribe);
    }

    protected function subscribeNewsletter($email, StockAlertEvent $event)
    {
        $customer = NewsletterQuery::create()->findOneByEmail($email);

        if (!$customer) {

            // In the language of the storefront the visitor subscribed from, as the alert itself is.
            $newsletter = new NewsletterEvent($email, (string) $event->getLocale());
            $this->dispatcher->dispatch($newsletter, TheliaEvents::NEWSLETTER_SUBSCRIBE);

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

            $this->dispatcher->dispatch(
                $availabilityEvent,
                StockAlertEvents::STOCK_ALERT_CHECK_AVAILABILITY
            );

            if ($availabilityEvent->isAvailable()) {
                $subscribers = RestockingAlertQuery::create()
                    ->filterByProductSaleElementsId($productSaleElementUpdateEvent->getProductSaleElementId())
                    ->find();

                foreach ($subscribers as $subscriber) {
                    try {
                        $this->sendEmail($subscriber);
                        $subscriber->delete();
                    } catch (\Throwable $exception) {
                        // The subscriber stays registered for the next time the product is back.
                        // The text of a mailer's exception may quote the address: the class and the sale element only.
                        Tlog::getInstance()->error(\sprintf('Stock alert: the message of a restocking alert for sale element %d was not sent (%s)', $subscriber->getProductSaleElementsId(), $exception::class));
                    }
                }
            }
        }
    }

    /**
     * @throws \RuntimeException when the message cannot be built or does not leave
     */
    public function sendEmail(RestockingAlert $subscriber)
    {
        $this->restockingMailer->sendBackInStock(
            (string) $subscriber->getEmail(),
            (string) ($subscriber->getLocale() ?: Lang::getDefaultLanguage()->getLocale()),
            (int) $subscriber->getProductSaleElementsId(),
        );
    }

    /**
     * What the message to the administrator lists about each product: its reference, its title, its page on the
     * shop and its page in the back office.
     *
     * @param array<int, int|string> $productIds
     *
     * @return list<array{id: int, ref: string, title: string, url: string, admin_url: string}>
     */
    private function describeProducts(array $productIds, string $locale): array
    {
        $url = URL::getInstance();
        $products = [];

        foreach (ProductQuery::create()->filterById($productIds, Criteria::IN)->orderById()->find() as $product) {
            try {
                $productUrl = (string) $url->retrieve('product', $product->getId(), $locale)->toString();
            } catch (\Throwable) {
                $productUrl = $url->absoluteUrl('/product/'.$product->getId());
            }

            $products[] = [
                'id' => $product->getId(),
                'ref' => (string) $product->getRef(),
                'title' => (string) $product->setLocale($locale)->getTitle(),
                'url' => $productUrl,
                'admin_url' => $url->absoluteUrl('/admin/products/update', ['product_id' => $product->getId()]),
            ];
        }

        return $products;
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
                $this->sendEmailForAdmin($config['emails'], $productIds);
            }
        }
    }

    public function sendEmailForAdmin($emails, $productIds): void
    {
        $config = StockAlert::getConfig();

        if ($config['notify']) {
            $locale = Lang::getDefaultLanguage()->getLocale();

            $contactEmail = ConfigQuery::read('store_email');

            if ($contactEmail) {
                $storeName = ConfigQuery::read('store_name');

                $to = [];

                foreach ($emails as $recipient) {
                    $to[$recipient] = $storeName;
                }

                $this->mailer->sendEmailMessage(
                    'stockalert_administrator',
                    [ $contactEmail => $storeName ],
                    $to,
                    [
                        'locale' => $locale,
                        'products_id' => $productIds,
                        'products' => $this->describeProducts($productIds, $locale),
                    ],
                    $locale
                );

                Tlog::getInstance()->debug("Stock Alert sent to administrator " . implode(', ', $emails));
            } else {
                Tlog::getInstance()->debug("Restocking Alert: no contact email is defined !");
            }
        }
    }
}
