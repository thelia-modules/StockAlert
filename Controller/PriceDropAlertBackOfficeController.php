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

namespace StockAlert\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use StockAlert\Form\PriceDropAlertConfig;
use StockAlert\Model\PriceDropAlertQuery;
use StockAlert\StockAlert;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;
use Twig\Environment;

/**
 * Back-office of the price drop alert: the settings of the feature, and a
 * follow-up screen counting the subscriptions product by product.
 *
 * The follow-up screen never renders an email address: a shopkeeper needs to
 * know what is awaited and how much, not who awaits it, and the subscribers of
 * a product are personal data the module already mails on its own.
 */
#[Route('/admin/modules/StockAlert/price-drop', name: 'stockalert.price_drop')]
class PriceDropAlertBackOfficeController extends BaseAdminController
{
    private const PAGE_SIZE = 25;

    private const LIST_TEMPLATE = '@StockAlertModule/backOffice/default-twig/StockAlert/price-drop-list.html.twig';

    #[Route('/save', name: '.save', methods: ['POST'])]
    public function save(ParserContext $parserContext): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['StockAlert'], AccessManager::UPDATE)) {
            return $response;
        }

        $errorMessage = null;
        $form = $this->createForm(PriceDropAlertConfig::getName());

        try {
            $data = $this->validateForm($form)->getData();

            ConfigQuery::write(StockAlert::CONFIG_PRICE_DROP_ENABLED, $data['enabled'] ? '1' : '0');
            ConfigQuery::write(StockAlert::CONFIG_PRICE_DROP_THRESHOLD_PERCENT, (string) $data['threshold_percent']);
            ConfigQuery::write(StockAlert::CONFIG_PRICE_DROP_EXPIRATION_DAYS, (string) $data['expiration_days']);
            ConfigQuery::write(StockAlert::CONFIG_PRICE_DROP_MAX_PER_EMAIL, (string) $data['max_per_email']);
            ConfigQuery::write(StockAlert::CONFIG_PRICE_DROP_BATCH_SIZE, (string) $data['batch_size']);
        } catch (FormValidationException $exception) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($exception);
        } catch (\Exception $exception) {
            $errorMessage = $exception->getMessage();
        }

        if (null !== $errorMessage) {
            $form->setErrorMessage($errorMessage);

            $parserContext
                ->addForm($form)
                ->setGeneralError($errorMessage);
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl('/admin/module/'.StockAlert::getModuleCode()),
        );
    }

    #[Route('', name: '.list', methods: ['GET'])]
    public function list(Request $request, Environment $twig): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['StockAlert'], AccessManager::VIEW)) {
            return $response;
        }

        $page = max(1, (int) $request->query->get('page', 1));

        $total = PriceDropAlertQuery::create()->countFollowedProducts();
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $lastPage);

        $rows = self::describe(PriceDropAlertQuery::create()->followedProductsPage($page, self::PAGE_SIZE), $request->getLocale());

        return new Response($twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
            'total' => $total,
            'pages' => $lastPage,
            'current_page' => $page,
        ]));
    }

    /**
     * @param list<array{product_id: int, subscription_count: int, queued_count: int}> $groups
     *
     * @return list<array{product_id: int, product_title: string, ref: string, subscriptions: int, queued: int}>
     */
    private static function describe(array $groups, string $locale): array
    {
        $products = self::indexProducts(array_column($groups, 'product_id'), $locale);

        $rows = [];
        foreach ($groups as $group) {
            $product = $products[$group['product_id']] ?? null;

            $rows[] = [
                'product_id' => $group['product_id'],
                'product_title' => self::productTitle($group['product_id'], $product),
                'ref' => $product?->getRef() ?? '',
                'subscriptions' => $group['subscription_count'],
                'queued' => $group['queued_count'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, Product>
     */
    private static function indexProducts(array $productIds, string $locale): array
    {
        if ([] === $productIds) {
            return [];
        }

        $products = [];
        foreach (ProductQuery::create()->filterById($productIds, Criteria::IN)->find() as $product) {
            $products[(int) $product->getId()] = $product->setLocale($locale);
        }

        return $products;
    }

    /**
     * A product with no translation in the administrator's language has no title
     * at all, so the reference, then the identifier, stand in for it.
     */
    private static function productTitle(int $productId, ?Product $product): string
    {
        $title = $product?->getTitle();
        if (null !== $title && '' !== $title) {
            return $title;
        }

        $reference = $product?->getRef();

        return null !== $reference && '' !== $reference ? $reference : (string) $productId;
    }
}
