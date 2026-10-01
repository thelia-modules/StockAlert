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

namespace StockAlert\Service;

use StockAlert\StockAlert;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

/**
 * Sends the two messages a customer receives about a product out of stock, in the language they asked in:
 * the acknowledgement of the subscription (`stockalert_subscribed`) and the product being back
 * (`stockalert_customer`).
 *
 * The variables of both templates, which code that sends the messages itself must give as well:
 * `locale`, `product_id`, `pse_id`, `product_title` (in the language of the customer, else in the default
 * language of the shop, else the reference), `product_url` (the product page, absolute) and `combination`
 * (the attribute values of the sale element, in the language of the customer, empty when it has none).
 */
final readonly class RestockingMailer
{
    public function __construct(
        private MailerFactory $mailer,
        private URL $url,
    ) {
    }

    /**
     * @throws \RuntimeException when the message cannot be built or does not leave
     */
    public function sendSubscribed(string $email, string $locale, int $productSaleElementsId): void
    {
        $this->send(StockAlert::MESSAGE_SUBSCRIBED, $email, $locale, $productSaleElementsId);
    }

    /**
     * @throws \RuntimeException when the message cannot be built or does not leave
     */
    public function sendBackInStock(string $email, string $locale, int $productSaleElementsId): void
    {
        $this->send(StockAlert::MESSAGE_CUSTOMER, $email, $locale, $productSaleElementsId);
    }

    private function send(string $message, string $email, string $locale, int $productSaleElementsId): void
    {
        $contactEmail = ConfigQuery::read('store_email');

        if (!$contactEmail) {
            throw new \RuntimeException('The shop has no e-mail address to send the stock alerts from.');
        }

        $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId);

        if (null === $productSaleElements) {
            throw new \RuntimeException(\sprintf('The sale element %d does not exist.', $productSaleElementsId));
        }

        $product = $productSaleElements->getProduct();
        $storeName = (string) ConfigQuery::read('store_name');

        $this->mailer->sendEmailMessageOrFail(
            $message,
            [$contactEmail => $storeName],
            [$email => $storeName],
            [
                'locale' => $locale,
                'product_id' => $product->getId(),
                'pse_id' => $productSaleElementsId,
                'product_title' => $this->title($product, $locale) ?? (string) $productSaleElements->getRef(),
                'product_url' => $this->productUrl($product->getId(), $locale),
                'combination' => $this->combination($productSaleElements, $locale),
            ],
            $locale,
        );
    }

    private function title(Product $product, string $locale): ?string
    {
        foreach (array_unique([$locale, Lang::getDefaultLanguage()->getLocale()]) as $candidate) {
            $title = trim((string) $product->setLocale($candidate)->getTitle());

            if ('' !== $title) {
                return $title;
            }
        }

        return null;
    }

    private function productUrl(int $productId, string $locale): string
    {
        try {
            return (string) $this->url->retrieve('product', $productId, $locale)->toString();
        } catch (\Throwable) {
            return $this->url->absoluteUrl('/product/'.$productId);
        }
    }

    private function combination(ProductSaleElements $productSaleElements, string $locale): string
    {
        $values = [];

        foreach ($productSaleElements->getAttributeCombinations() as $combination) {
            $value = $combination->getAttributeAv();

            if (null === $value) {
                continue;
            }

            foreach (array_unique([$locale, Lang::getDefaultLanguage()->getLocale()]) as $candidate) {
                $title = trim((string) $value->setLocale($candidate)->getTitle());

                if ('' !== $title) {
                    $values[] = $title;

                    break;
                }
            }
        }

        return implode(' / ', $values);
    }
}
