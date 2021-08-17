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

namespace StockAlert\Event;

use StockAlert\Model\Base\RestockingAlert;
use Thelia\Core\Event\ActionEvent;

/**
 * Class StockAlertEvent
 * @package StockAlert\Event
 * @author Baixas Alban <abaixas@openstudio.fr>
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StockAlertEvent extends ActionEvent
{

    /** @var  int */
    private $productSaleElementsId;

    /** @var  string */
    private $email;

    /** @var  string */
    private $locale;

    /** @var  RestockingAlert */
    private $restockingAlert;

    /** @var boolean */
    private $subscribeToNewsLetter;

    /**
     * @param $productSaleElementsId
     * @param $email
     */
    public function __construct($productSaleElementsId, $email, $subscribeToNewsLetter, $locale)
    {
        $this->setEmail($email);
        $this->setProductSaleElementsId($productSaleElementsId);
        $this->setLocale($locale);
        $this->setSubscribeToNewsLetter($subscribeToNewsLetter);
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProductSaleElementsId()
    {
        return $this->productSaleElementsId;
    }

    /**
     * @param mixed $productSaleElementsId
     */
    public function setProductSaleElementsId($productSaleElementsId)
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    /**
     * @return RestockingAlert
     */
    public function getRestockingAlert()
    {
        return $this->restockingAlert;
    }

    /**
     * @param RestockingAlert $restockingAlert
     */
    public function setRestockingAlert($restockingAlert)
    {
        $this->restockingAlert = $restockingAlert;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @param boolean $subscribeToNewsLetter
     */
    public function setSubscribeToNewsLetter($subscribeToNewsLetter)
    {
        $this->subscribeToNewsLetter = $subscribeToNewsLetter;
    }

    /**
     * @return boolean
     */
    public function getSubscribeToNewsLetter()
    {
        return $this->subscribeToNewsLetter;
    }
}
