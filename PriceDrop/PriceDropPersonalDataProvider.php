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

namespace StockAlert\PriceDrop;

use Propel\Runtime\ActiveQuery\Criteria;
use StockAlert\Model\PriceDropAlert;
use StockAlert\Model\PriceDropAlertQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * A price alert is personal data: an address, a product someone wants, a price
 * they found too high. The rows a customer owns, by account or by address, go
 * into the export and disappear with the anonymization.
 *
 * The core rewrites the account email before it hands the customer to the
 * providers: by then the address branch matches nothing. So the rows are also
 * removed ahead of the core action, while the customer still carries the
 * address the visitor typed.
 */
final readonly class PriceDropPersonalDataProvider implements CustomerPersonalDataProviderInterface, EventSubscriberInterface
{
    /** Ahead of the core action (128), which anonymizes the account before it calls the providers. */
    public const ANONYMIZE_PRIORITY = 256;

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CUSTOMER_ANONYMIZE => ['onCustomerAnonymize', self::ANONYMIZE_PRIORITY],
        ];
    }

    public function onCustomerAnonymize(CustomerAnonymizeEvent $event): void
    {
        $this->anonymizePersonalData($event->getCustomer());
    }

    public function getPersonalDataSectionName(): string
    {
        return 'stockalert_price_drop_alerts';
    }

    public function exportPersonalData(Customer $customer): array
    {
        return array_map(
            static fn (PriceDropAlert $alert): array => [
                'product_sale_elements_id' => $alert->getProductSaleElementsId(),
                'email' => $alert->getEmail(),
                'locale' => $alert->getLocale(),
                'currency_id' => $alert->getCurrencyId(),
                'reference_price' => (float) $alert->getReferencePrice(),
                'status' => $alert->getStatus(),
                'created_at' => $alert->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'expires_at' => $alert->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            ],
            $this->alertsOf($customer)->find()->getData(),
        );
    }

    public function anonymizePersonalData(Customer $customer): void
    {
        $this->alertsOf($customer)->delete();
    }

    private function alertsOf(Customer $customer): PriceDropAlertQuery
    {
        return PriceDropAlertQuery::create()
            ->filterByCustomerId($customer->getId())
            ->_or()
            ->filterByEmail(mb_strtolower((string) $customer->getEmail()), Criteria::EQUAL)
            ->orderById();
    }
}
