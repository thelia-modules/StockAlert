<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace StockAlert\Form;

use StockAlert\StockAlert;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Form\Type\Field\ProductSaleElementsIdType;
use Thelia\Core\Form\Type\ProductSaleElementsType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class RestockingAlertSubscribe
 * @package RestockingAlert\Form
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <julien@thelia.net>
 */
class StockAlertSubscribe extends BaseForm
{

    public function init(Request $request, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator, FormFactoryBuilderInterface $formFactoryBuilder, ValidatorBuilder $validationBuilder, TokenStorageInterface $tokenStorage, string $type = "Symfony\Component\Form\Extension\Core\Type\FormType", array $data = [], array $options = [], ?CsrfTokenManagerInterface $csrfTokenManager = null): void
    {
        // To prevent "extra_fields_message" in local/modules/StockAlert/Controller/StockAlertFrontOfficeController.php:35
        $options['csrf_protection'] = false;
        parent::init($request, $eventDispatcher, $translator, $formFactoryBuilder, $validationBuilder, $tokenStorage, $type, $data, $options, $csrfTokenManager);
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'product_sale_elements_id',
                ProductSaleElementsIdType::class,
                [
                    'required'     => true,
                    "label" => Translator::getInstance()->trans("Product", [], StockAlert::MESSAGE_DOMAIN),
                    "label_attr" => [
                        "for" => "product_sale_elements_id"
                    ]
                ]
            )
            ->add(
                "email",
                EmailType::class,
                [
                    "constraints" => [
                        new NotBlank(),
                        new Email()
                    ],
                    "label" => Translator::getInstance()->trans("Email Address", [], StockAlert::MESSAGE_DOMAIN),
                    "label_attr" => [
                        "for" => "email"
                    ]
                ]
            );

        // The newsletter checkbox is the shop's choice (setting stockalert_newsletter, off by default): a form
        // without the field refuses nothing and subscribes nobody, the visitor is never shown a box that does nothing.
        if (StockAlert::getConfig()['newsletter']) {
            $this->formBuilder->add("newsletter", CheckboxType::class, array(
                "label" => Translator::getInstance()->trans('I would like to receive the newsletter or the latest news.', [], 'stockalert.fo.default'),
                "label_attr" => array(
                    "for" => "newsletter",
                ),
                "required" => false,
            ));
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'stockalert_subscribe_form';
    }
}
