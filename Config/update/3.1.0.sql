-- ---------------------------------------------------------------------
-- price_drop_alert
-- ---------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
CREATE TABLE IF NOT EXISTS `price_drop_alert`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `customer_id` INTEGER,
    `email` VARCHAR(255) NOT NULL,
    `locale` VARCHAR(45),
    `currency_id` INTEGER NOT NULL,
    `reference_price` DECIMAL(16,6) NOT NULL,
    `new_price` DECIMAL(16,6),
    `status` VARCHAR(10) DEFAULT 'active' NOT NULL,
    `attempts` SMALLINT DEFAULT 0 NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `queued_at` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_price_drop_alert_pse_email` (`product_sale_elements_id`, `email`),
    INDEX `idx_price_drop_alert_email` (`email`),
    INDEX `idx_price_drop_alert_expires_at` (`expires_at`),
    INDEX `idx_price_drop_alert_status` (`status`),
    INDEX `fi_price_drop_alert_customer_id` (`customer_id`),
    INDEX `fi_price_drop_alert_currency_id` (`currency_id`),
    CONSTRAINT `fk_price_drop_alert_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_price_drop_alert_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_price_drop_alert_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
