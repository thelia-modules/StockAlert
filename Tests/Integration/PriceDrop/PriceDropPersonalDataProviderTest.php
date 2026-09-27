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

namespace StockAlert\Tests\Integration\PriceDrop;

use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\PriceDrop\PriceDropPersonalDataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;

final class PriceDropPersonalDataProviderTest extends PriceDropTestCase
{
    private PriceDropPersonalDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = $this->getService(PriceDropPersonalDataProvider::class);
    }

    public function testTheProviderIsOneTheCoreCollects(): void
    {
        self::assertInstanceOf(CustomerPersonalDataProviderInterface::class, $this->provider);
        self::assertSame('stockalert_price_drop_alerts', $this->provider->getPersonalDataSectionName());
    }

    public function testTheExportListsTheAlertsOfTheAccountAndOfItsAddress(): void
    {
        $customer = $this->customer();
        $productSaleElements = $this->productPricedAt(40.0)->getDefaultSaleElements();
        $byAccount = $this->activeAlert($productSaleElements, $this->uniqueEmail('other-address'), 40.0, ['customerId' => $customer->getId()]);
        $byAddress = $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $customer->getEmail(), 10.0);
        $this->activeAlert($this->productPricedAt(10.0)->getDefaultSaleElements(), $this->uniqueEmail('stranger'), 10.0);

        $export = $this->provider->exportPersonalData($customer);

        self::assertCount(2, $export);
        self::assertSame([$byAccount->getProductSaleElementsId(), $byAddress->getProductSaleElementsId()], array_column($export, 'product_sale_elements_id'));
        self::assertSame(40.0, $export[0]['reference_price']);
        self::assertSame('active', $export[0]['status']);
        self::assertArrayHasKey('expires_at', $export[0]);
    }

    public function testAnonymizingRemovesTheCustomerAlertsAndNobodyElses(): void
    {
        $customer = $this->customer();
        $mine = $this->activeAlert($this->productPricedAt(40.0)->getDefaultSaleElements(), $customer->getEmail(), 40.0, ['customerId' => $customer->getId()]);
        $stranger = $this->activeAlert($this->productPricedAt(40.0)->getDefaultSaleElements(), $this->uniqueEmail('stranger'), 40.0);

        $this->provider->anonymizePersonalData($customer);

        self::assertNull(PriceDropAlertQuery::create()->findPk($mine->getId()));
        self::assertNotNull(PriceDropAlertQuery::create()->findPk($stranger->getId()));
        self::assertSame([], $this->provider->exportPersonalData($customer));
    }

    public function testTheCoreAnonymizationRemovesTheAlertsMadeWithTheAccountAddress(): void
    {
        $customer = $this->customer();
        $byAddress = $this->activeAlert($this->productPricedAt(40.0)->getDefaultSaleElements(), $customer->getEmail(), 40.0);
        $byAccount = $this->activeAlert($this->productPricedAt(40.0)->getDefaultSaleElements(), $this->uniqueEmail('other-address'), 40.0, ['customerId' => $customer->getId()]);
        $stranger = $this->activeAlert($this->productPricedAt(40.0)->getDefaultSaleElements(), $this->uniqueEmail('stranger'), 40.0);

        $this->getService(EventDispatcherInterface::class)->dispatch(new CustomerAnonymizeEvent($customer), TheliaEvents::CUSTOMER_ANONYMIZE);

        self::assertNull(PriceDropAlertQuery::create()->findPk($byAddress->getId()), 'the core rewrites the address before it calls the providers: the alert must go before that');
        self::assertNull(PriceDropAlertQuery::create()->findPk($byAccount->getId()));
        self::assertNotNull(PriceDropAlertQuery::create()->findPk($stranger->getId()));
    }

    private function customer(): Customer
    {
        return $this->factory->customer(CustomerTitleQuery::create()->findOne(), ['email' => $this->uniqueEmail('customer')]);
    }
}
