-- ---------------------------------------------------------------------
-- stock_product_alert
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `stock_product_alert`;

CREATE TABLE `stock_product_alert`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fi_stock_pse_alert_product_id` (`product_id`),
    CONSTRAINT `fk_stock_pse_alert_product_id`
        FOREIGN KEY (`product_id`)
            REFERENCES `product` (`id`)
            ON DELETE CASCADE
) ENGINE=InnoDB;