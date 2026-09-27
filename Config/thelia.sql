
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- restocking_alert
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `restocking_alert`;

CREATE TABLE `restocking_alert`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `email` VARCHAR(255),
    `locale` VARCHAR(45),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `FI_restocking_alert_product_sale_elements_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_restocking_alert_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- price_drop_alert
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `price_drop_alert`;

CREATE TABLE `price_drop_alert`
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

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
