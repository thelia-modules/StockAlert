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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class StockAlertConfig
 * @package StockAlert\Form
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertConfig extends BaseForm
{
    /** @var Translator $translator */
    protected $translator;

    public function checkEmails($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        $value = trim($value);

        if ("" === trim($value) && !empty($data["enabled"])) {
            $context->addViolation(
                $this->trans(
                    "The Emails can not be empty",
                    [
                        "%id" => $value,
                    ]
                )
            );
        }

        $emails = explode(',', $value);
        foreach ($emails as $email) {
            if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $context->addViolation(
                    $this->trans(
                        "'%email' is not a valid email address",
                        ["%email" => $email]
                    )
                );
            }
        }
    }

    protected function trans($id, $parameters = [])
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, StockAlert::MESSAGE_DOMAIN);
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'stockalert_config_form';
    }

    protected function buildForm()
    {
        $config = StockAlert::getConfig();

        $this->formBuilder
            ->add(
                'enabled',
                CheckboxType::class,
                [
                    "required" => false,
                    "data" => $config['enabled'],
                    "label" => Translator::getInstance()->trans("Enabled", [], StockAlert::MESSAGE_DOMAIN),
                    "label_attr" => [
                        "for" => "enabled"
                    ]
                ]
            )
            ->add(
                'threshold',
                IntegerType::class,
                [
                    "required" => true,
                    "constraints" => [
                        new NotBlank(),
                    ],
                    "data" => $config['threshold'],
                    "label" => Translator::getInstance()->trans("Threshold", [], StockAlert::MESSAGE_DOMAIN),
                    "label_attr" => [
                        "for" => "email",
                        "help" => Translator::getInstance()->trans(
                            "You will recieve a notification when the quantity in stock is lower or equal to this value.",
                            [],
                            StockAlert::MESSAGE_DOMAIN
                        ),
                    ]
                ]
            )
            ->add(
                "emails",
                TextType::class,
                [
                    "constraints" => [
                        new Callback(
                            [$this, "checkEmails"]
                        ),
                    ],
                    "required" => false,
                    "data" => implode(',', $config['emails']),
                    "label" => Translator::getInstance()->trans(
                        "Email Address",
                        [],
                        StockAlert::MESSAGE_DOMAIN
                    ),
                    "label_attr" => [
                        "for" => "emails",
                        "help" => Translator::getInstance()->trans(
                            "A comma separated list of email that will recieve notifications",
                            [],
                            StockAlert::MESSAGE_DOMAIN
                        ),
                    ]
                ]
            );
    }
}
