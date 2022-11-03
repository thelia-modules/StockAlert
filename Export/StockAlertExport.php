<?php

namespace StockAlert\Export;

use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;

class StockAlertExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'stock_alert';
    const USE_RANGE_DATE = true;

    protected $orderAndAliases = [
        'ref' => 'Référence produit',
        'nbSubscriber' => 'Nombre d\'inscrit',
    ];

    protected function getData()
    {
        $con = Propel::getConnection();

        $query = '
            SELECT pse.ref, COUNT(product_sale_elements_id) as nbSubscriber
            FROM restocking_alert as ra
            JOIN product_sale_elements as pse ON ra.product_sale_elements_id = pse.id
            WHERE ra.created_at >= :start AND ra.created_at <= :end
            GROUP BY product_sale_elements_id
            ORDER BY nbSubscriber DESC';

        $stmt = $con->prepare($query);
        $stmt->bindValue('start', $this->rangeDate['start']->format('Y-m-d H:i:s'));
        $stmt->bindValue('end', $this->rangeDate['end']->format('Y-m-d H:i:s'));
        $stmt->execute();

        $filename = THELIA_CACHE_DIR . '/export/' . 'stock_alert.json';

        if (file_exists($filename)) {
            unlink($filename);
        }

        if(!$stmt->fetch(\PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode(['ref' => 'Aucune donnée', 'nbSubscriber' => '']), FILE_APPEND);

            return $filename;
        }

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row) . "\r\n", FILE_APPEND);
        }

        return $filename;
    }
}