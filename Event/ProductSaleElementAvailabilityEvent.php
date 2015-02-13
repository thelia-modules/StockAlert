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

use Thelia\Core\Event\ProductSaleElement\ProductSaleElementEvent;
use Thelia\Model\ProductSaleElements;

/**
 * Class ProductSaleElementAvailabilityEvent
 * @package StockAlert\Event
 * @author Julien Chanséaume <julien@thelia.net>
 */
class ProductSaleElementAvailabilityEvent extends ProductSaleElementEvent
{

    /** @var bool */
    protected $available = true;


    public function __construct(ProductSaleElements $product_sale_element = null)
    {
        $this->product_sale_element = $product_sale_element;
    }

    /**
     * @return boolean
     */
    public function isAvailable()
    {
        return $this->available;
    }

    /**
     * @param boolean $available
     */
    public function setAvailable($available)
    {
        $this->available = $available;

        return $this;
    }
}
