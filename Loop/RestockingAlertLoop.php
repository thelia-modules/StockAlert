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


namespace StockAlert\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use StockAlert\Model\RestockingAlert;
use StockAlert\Model\RestockingAlertQuery;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class RestockingAlertLoop
 * @package StockAlert\Loop
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class RestockingAlertLoop extends BaseLoop implements PropelSearchLoopInterface
{

    protected $timestampable = true;

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var RestockingAlert $item */
        foreach ($loopResult->getResultDataCollection() as $item) {

            $loopResultRow = new LoopResultRow($item);

            $loopResultRow
                ->set("ID", $item->getId())
                ->set("PRODUCT_SALE_ELEMENTS_ID", $item->getProductSaleElementsId())
                ->set("EMAIL", $item->getEmail())
                ->set("LOCALE", $item->getLocale())
            ;

            $this->addOutputFields($loopResultRow, $item);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('email'),
            Argument::createIntListTypeArgument('product_sale_element'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id',
                            'id_reverse',
                            'product_sale_element_id',
                            'product_sale_element_id_reverse',
                            'email',
                            'email_reverse',
                            'created',
                            'created_reverse',
                            'updated',
                            'updated_reverse',
                            'random'
                        ]
                    )
                ),
                'id_reverse'
            )
        );
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $query = RestockingAlertQuery::create();

        $id = $this->getArgValue('id');
        if (!empty($id)) {
            $query->filterById($id, Criteria::IN);
        }

        $email = $this->getArgValue('email');
        if (!empty($email)) {
            $query->filterByEmail('%' . $email . '%', Criteria::LIKE);
        }

        $id = $this->getArgValue('product_sale_element');
        if (!empty($id)) {
            $query->filterByProductSaleElementsId($id, Criteria::IN);
        }

        $orders = $this->getArgValue('order');

        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $query->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $query->orderById(Criteria::DESC);
                    break;
                case "product_sale_element_id":
                    $query->orderByProductSaleElementsId(Criteria::ASC);
                    break;
                case "product_sale_element_id_reverse":
                    $query->orderByProductSaleElementsId(Criteria::DESC);
                    break;
                case "email_id":
                    $query->orderByEmail(Criteria::ASC);
                    break;
                case "email_reverse":
                    $query->orderByEmail(Criteria::DESC);
                    break;
                case "created":
                    $query->addAscendingOrderByColumn('created_at');
                    break;
                case "created_reverse":
                    $query->addDescendingOrderByColumn('created_at');
                    break;
                case "updated":
                    $query->addAscendingOrderByColumn('updated_at');
                    break;
                case "updated_reverse":
                    $query->addDescendingOrderByColumn('updated_at');
                    break;
                case "random":
                    $query->clearOrderByColumns();
                    $query->addAscendingOrderByColumn('RAND()');
                    break(2);
            }
        }

        return $query;
    }
}
