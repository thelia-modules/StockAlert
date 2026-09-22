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
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * A price alert is personal data: an address, a product someone wants, a price
 * they found too high. The rows a customer owns, by account or by address, go
 * into the export and disappear with the anonymization.
 */
final readonly class PriceDropPersonalDataProvider implements CustomerPersonalDataProviderInterface
{
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
