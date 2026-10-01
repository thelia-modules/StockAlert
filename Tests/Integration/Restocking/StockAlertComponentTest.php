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

namespace StockAlert\Tests\Integration\Restocking;

use StockAlert\Event\StockAlertEvents;
use StockAlert\Exception\SubscriptionRefusedException;
use StockAlert\Twig\Organisms\StockAlert as StockAlertComponent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * What the visitor reads when they subscribe from the component: the message of a refusal written for them,
 * a generic message for anything else (never the text of an exception), the form's own message for a wrong
 * address, the acknowledgement unless the page asked to close its own window.
 */
final class StockAlertComponentTest extends RestockingTestCase
{
    private EventDispatcherInterface $dispatcher;

    private StockAlertComponent $component;

    private int $productSaleElementsId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->productSaleElementsId = $this->productOutOfStock()->getDefaultSaleElements()->getId();
        $this->component = $this->getService(StockAlertComponent::class);
        $this->component->pseId = $this->productSaleElementsId;
    }

    public function testASubscriptionIsAcknowledged(): void
    {
        $this->submit($this->uniqueEmail());

        self::assertTrue($this->component->success);
        self::assertSame(1, $this->subscriptionsOf($this->productSaleElementsId));
        self::assertStringContainsString('back in stock', (string) $this->component->message);
        self::assertTrue($this->component->showSuccessMessage, 'shown unless the page asks otherwise');
    }

    public function testTheMessageOfARefusalIsShownAsItIs(): void
    {
        $this->listen(static function (): never {
            throw new SubscriptionRefusedException('This product is in stock');
        });

        $this->submit($this->uniqueEmail());

        self::assertFalse($this->component->success);
        self::assertSame('This product is in stock', $this->component->message);
        self::assertSame(0, $this->subscriptionsOf($this->productSaleElementsId));
    }

    public function testNothingButARefusalLetsItsMessageThrough(): void
    {
        $this->listen(static function (): never {
            throw new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation in /var/www/shop/vendor/secret.php');
        });

        $this->submit($this->uniqueEmail());

        self::assertFalse($this->component->success);
        self::assertSame('Something went wrong. Please try again later.', $this->component->message);
        self::assertStringNotContainsString('SQLSTATE', (string) $this->component->message);
    }

    public function testAWrongAddressIsToldInTheModulesOwnWords(): void
    {
        $this->submit('not an address');

        self::assertFalse($this->component->success);
        self::assertSame('Please enter a valid email address.', $this->component->message);
        self::assertSame(0, $this->subscriptionsOf($this->productSaleElementsId));
    }

    private function listen(callable $listener): void
    {
        $this->dispatcher->addListener(StockAlertEvents::STOCK_ALERT_SUBSCRIBE, $listener, 256);
    }

    private function submit(string $email): void
    {
        $this->component->formValues = ['email' => $email, 'product_sale_elements_id' => (string) $this->productSaleElementsId];
        $this->component->save();
    }
}
