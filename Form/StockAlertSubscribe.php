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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
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
            )
            // Add Newsletter checkbox
            ->add("newsletter", CheckboxType::class, array(
                "label" => Translator::getInstance()->trans('I would like to receive the newsletter or the latest news.'),
                "label_attr" => array(
                    "for" => "newsletter",
                ),
                "required" => false,
            ));
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'stockalert_subscribe_form';
    }
}
