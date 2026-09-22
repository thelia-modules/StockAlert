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

use StockAlert\StockAlert;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * The address only: the sale element followed comes from the live component's
 * own property, which the product page keeps in step with the selected variant.
 */
class PriceDropAlertSubscribe extends BaseForm
{
    public function init(Request $request, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator, FormFactoryBuilderInterface $formFactoryBuilder, ValidatorBuilder $validationBuilder, TokenStorageInterface $tokenStorage, string $type = \Symfony\Component\Form\Extension\Core\Type\FormType::class, array $data = [], array $options = [], ?CsrfTokenManagerInterface $csrfTokenManager = null): void
    {
        // Same choice as the restocking form: the product page is cacheable, so a
        // session-bound token would be replayed; the live action transport checks
        // the origin instead, and the module rate-limits the subscription itself.
        $options['csrf_protection'] = false;

        parent::init($request, $eventDispatcher, $translator, $formFactoryBuilder, $validationBuilder, $tokenStorage, $type, $data, $options, $csrfTokenManager);
    }

    protected function buildForm(): void
    {
        $this->formBuilder->add('email', EmailType::class, [
            'constraints' => [new NotBlank(), new Email(), new Length(max: 255)],
            'label' => Translator::getInstance()->trans('Email address', [], StockAlert::MESSAGE_DOMAIN),
            'label_attr' => ['for' => 'price_drop_email'],
            'attr' => ['autocomplete' => 'email', 'placeholder' => 'you@example.com'],
        ]);
    }

    public static function getName(): string
    {
        return 'stockalert_price_drop_subscribe_form';
    }
}
