<?php

namespace StockAlert\Service;

use StockAlert\StockAlert;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

class adminMailService
{
    protected $mailer;

    public function __construct(MailerFactory $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendEmailForAdmin($productIds)
    {
        $locale = Lang::getDefaultLanguage()->getLocale();

        $config = StockAlert::getConfig();

        $contactEmail = ConfigQuery::read('store_email');

        if ($contactEmail) {
            $storeName = ConfigQuery::read('store_name');

            $to = [];

            foreach ($config['emails'] as $recipient) {
                $to[$recipient] = $storeName;
            }

            $this->mailer->sendEmailMessage(
                'stockalert_administrator',
                [$contactEmail => $storeName],
                $to,
                [
                    'locale' => $locale,
                    'products_id' => $productIds
                ],
                $locale
            );

            Tlog::getInstance()->debug("Stock Alert sent to administrator " . implode(', ', $config['emails']));
        } else {
            Tlog::getInstance()->debug("Restocking Alert: no contact email is defined !");
        }
    }
}