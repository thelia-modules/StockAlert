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

namespace StockAlert\Twig\Organisms;

use StockAlert\PriceDrop\PriceDropSubscriptionService;
use StockAlert\PriceDrop\SubscriptionRefusal;
use StockAlert\PriceDrop\SubscriptionRefusedException;
use StockAlert\PriceDrop\SubscriptionRequest;
use StockAlert\PriceDrop\UnsubscribeTokenSigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Thelia\Core\Form\FormServiceInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

/**
 * The subscription form for one sale element. It lives inside the product
 * page's own live component, so `pseId` follows the selected variant from the
 * parent on every re-render.
 */
#[AsLiveComponent(name: 'PriceDropAlert', template: '@StockAlertModule/components/PriceDropAlert.html.twig')]
class PriceDropAlert extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    /** The front catalog of the module: I18n/frontOffice/default. */
    public const TRANSLATION_DOMAIN = 'stockalert.fo.default';

    #[LiveProp(updateFromParent: true)]
    public ?int $pseId = null;

    #[LiveProp(updateFromParent: true)]
    public ?float $taxedPrice = null;

    #[LiveProp]
    public bool $subscribed = false;

    #[LiveProp]
    public ?float $recordedTaxedPrice = null;

    #[LiveProp]
    public ?string $error = null;

    #[LiveProp]
    public ?string $unsubscribeUrl = null;

    public function __construct(
        private readonly FormServiceInterface $formService,
        private readonly PriceDropSubscriptionService $subscriptionService,
        private readonly UnsubscribeTokenSigner $unsubscribeTokenSigner,
        private readonly URL $url,
        private readonly TaxEngine $taxEngine,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[ExposeInTemplate('currency_code')]
    public function currencyCode(): string
    {
        return $this->session()->getCurrency()->getCode();
    }

    /**
     * The front session is Thelia's, which knows the visitor's language, currency
     * and account; the request stack only promises the Symfony interface.
     */
    private function session(): Session
    {
        $session = $this->requestStack->getSession();

        if (!$session instanceof Session) {
            throw new \LogicException('The price drop alert needs the shop session.');
        }

        return $session;
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formService->getFormByName('stockalert_price_drop_subscribe_form');
    }

    #[LiveAction]
    public function save(): void
    {
        $this->error = null;
        $this->submitForm();

        if (null === $this->pseId) {
            $this->error = $this->trans('Choose a variant first.');

            return;
        }

        $session = $this->session();
        $customer = $session->getCustomerUser();

        try {
            $outcome = $this->subscriptionService->subscribe(new SubscriptionRequest(
                $this->pseId,
                (string) $this->getForm()->get('email')->getData(),
                $session->getLang()->getLocale(),
                $session->getCurrency()->getId(),
                $customer?->getId(),
            ));
        } catch (SubscriptionRefusedException $refused) {
            $this->error = $this->refusalMessage($refused->refusal);

            return;
        }

        $this->recordedTaxedPrice = $this->taxedReference($outcome->untaxedReferencePrice, $session->getCurrency());
        $this->unsubscribeUrl = $this->url->absoluteUrl(
            '/module/stockalert/price-drop/unsubscribe/'.$this->unsubscribeTokenSigner->sign($outcome->priceDropAlertId, $outcome->expiresAt),
        );
        $this->subscribed = true;
        $this->resetForm();
    }

    /**
     * The price shown in the confirmation is the one the visitor saw, taxed like
     * the page shows it. When the page price is at hand it wins: the two figures
     * must match to the cent.
     */
    private function taxedReference(float $untaxedReference, Currency $currency): float
    {
        if (null !== $this->taxedPrice) {
            return $this->taxedPrice;
        }

        $productSaleElements = ProductSaleElementsQuery::create()->findPk($this->pseId);

        if (null === $productSaleElements) {
            return $untaxedReference;
        }

        $productSaleElements->setVirtualColumn('price_PRICE', $untaxedReference);

        // Same country as the price the page shows: the visitor's delivery country.
        return round($productSaleElements->getTaxedPrice($this->taxEngine->getDeliveryCountry()), 2);
    }

    private function refusalMessage(SubscriptionRefusal $refusal): string
    {
        return match ($refusal) {
            SubscriptionRefusal::RateLimited => $this->trans('Too many requests for now. Please try again in a little while.'),
            SubscriptionRefusal::QuotaExceeded => $this->trans('This address already follows the maximum number of products. Unsubscribe from one first.'),
            SubscriptionRefusal::Disabled => $this->trans('Price alerts are not available at the moment.'),
            SubscriptionRefusal::UnknownProductSaleElement,
            SubscriptionRefusal::UnknownCurrency => $this->trans('This variant cannot be followed. Please reload the page.'),
        };
    }

    private function trans(string $id): string
    {
        return Translator::getInstance()->trans($id, [], self::TRANSLATION_DOMAIN);
    }
}
