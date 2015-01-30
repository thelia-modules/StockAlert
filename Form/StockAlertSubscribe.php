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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class RestockingAlertSubscribe
 * @package RestockingAlert\Form
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien ChansÃ©aume <jchanseaume@openstudio.fr>
 */
class StockAlertSubscribe extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'product_sale_elements_id',
                'product_sale_elements_id',
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
                "email",
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
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'stockalert_subscribe_form';
    }
}
