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

namespace StockAlert\Tests\Http;

use Thelia\Test\WebIntegrationTestCase;

/**
 * A request to /module/stockalert that no form of the shop produced is refused with the answer of a refusal,
 * never with a server error.
 */
final class SubscribeForgedRequestTest extends WebIntegrationTestCase
{
    private const PATH = '/module/stockalert';

    public function testAForgedAjaxRequestGetsAJsonRefusal(): void
    {
        $this->client->request('POST', self::PATH, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($data['success']);
    }

    public function testAForgedFormGoesBackToTheHomePage(): void
    {
        $this->client->request('POST', self::PATH, ['stockalert_subscribe_form' => ['email' => 'x']]);

        $response = $this->client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function testASuccessUrlOfAnotherSiteIsNotFollowed(): void
    {
        $this->client->request('POST', self::PATH, ['stockalert_subscribe_form' => ['success_url' => 'https://elsewhere.example/phish']]);

        self::assertSame('/', $this->client->getResponse()->headers->get('Location'));
    }
}
