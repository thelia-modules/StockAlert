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

namespace StockAlert\Form;

use StockAlert\PriceDrop\PriceDropConfig;
use StockAlert\StockAlert;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Settings of the price drop alert, as the back-office configuration screen of
 * the module edits them. Reading goes through {@see PriceDropConfig} so the
 * screen and the runtime never disagree on a default.
 */
class PriceDropAlertConfig extends BaseForm
{
    public static function getName(): string
    {
        return 'stockalert_price_drop_config_form';
    }

    protected function buildForm(): void
    {
        $config = new PriceDropConfig();

        $this->formBuilder
            ->add(
                'enabled',
                CheckboxType::class,
                [
                    'required' => false,
                    'data' => $config->isEnabled(),
                    'label' => $this->translate('Price drop alert enabled'),
                    'label_attr' => [
                        'for' => 'price_drop_enabled',
                    ],
                ],
            )
            ->add(
                'threshold_percent',
                NumberType::class,
                [
                    'required' => true,
                    'scale' => 2,
                    'data' => $config->thresholdPercent(),
                    'constraints' => [
                        new NotBlank(),
                        new Range(min: 0, max: 100),
                    ],
                    'label' => $this->translate('Price drop threshold (%)'),
                    'label_attr' => [
                        'for' => 'price_drop_threshold_percent',
                        'help' => $this->translate('A subscriber is notified only when the new price is lower than the price seen by at least this percentage.'),
                    ],
                ],
            )
            ->add(
                'expiration_days',
                IntegerType::class,
                [
                    'required' => true,
                    'data' => $config->expirationDays(),
                    'constraints' => [
                        new NotBlank(),
                        new Range(min: 1),
                    ],
                    'label' => $this->translate('Subscription lifetime (days)'),
                    'label_attr' => [
                        'for' => 'price_drop_expiration_days',
                        'help' => $this->translate('A subscription that never saw a price drop is dropped after this many days.'),
                    ],
                ],
            )
            ->add(
                'max_per_email',
                IntegerType::class,
                [
                    'required' => true,
                    'data' => $config->maxSubscriptionsPerEmail(),
                    'constraints' => [
                        new NotBlank(),
                        new Range(min: 1),
                    ],
                    'label' => $this->translate('Maximum subscriptions per email address'),
                    'label_attr' => [
                        'for' => 'price_drop_max_per_email',
                        'help' => $this->translate('A visitor who reached this number of subscriptions cannot add another one.'),
                    ],
                ],
            )
            ->add(
                'batch_size',
                IntegerType::class,
                [
                    'required' => true,
                    'data' => $config->batchSize(),
                    'constraints' => [
                        new NotBlank(),
                        new Range(min: 1),
                    ],
                    'label' => $this->translate('Alerts sent per run'),
                    'label_attr' => [
                        'for' => 'price_drop_batch_size',
                        'help' => $this->translate('Number of pending alerts the scheduled command sends on each run.'),
                    ],
                ],
            );
    }

    private function translate(string $identifier): string
    {
        return Translator::getInstance()->trans($identifier, [], StockAlert::MESSAGE_DOMAIN);
    }
}
