-- Database Schema for Enterprise Retail POS

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Organization & Setup
-- --------------------------------------------------------

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `currency_symbol` varchar(10) DEFAULT 'PKR',
  `address` text,
  `phone` varchar(50),
  `email` varchar(100),
  `fiscal_year_start` date NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `store_code` varchar(50) NOT NULL,
  `address` text,
  `phone` varchar(50),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL, -- Null implies central warehouse
  `warehouse_name` varchar(255) NOT NULL,
  `location` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `terminals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. User Management & Security (RBAC)
-- --------------------------------------------------------

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL, -- Admin, Manager, Cashier
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(100) NOT NULL UNIQUE, -- e.g., 'pos.access', 'reports.view'
  `label` varchar(255),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100),
  `email` varchar(100),
  `phone` varchar(50),
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`),
  FOREIGN KEY (`terminal_id`) REFERENCES `terminals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text,
  `ip_address` varchar(45),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Accounting & Finance
-- --------------------------------------------------------

CREATE TABLE `chart_of_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_group` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `fiscal_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `gl_journal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `fiscal_year_id` int(11) NOT NULL,
  `journal_date` date NOT NULL,
  `reference` varchar(100), -- Invoice #, PO #
  `description` text,
  `posted_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `gl_journal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`journal_id`) REFERENCES `gl_journal`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Products & Inventory
-- --------------------------------------------------------

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `taxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL, -- Main SKU
  `barcode` varchar(100),
  `category_id` int(11),
  `tax_id` int(11),
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `is_tax_inclusive` tinyint(1) DEFAULT 0,
  `uom` varchar(20) DEFAULT 'Pcs',
  `cost_price` decimal(15,2) DEFAULT 0.00,
  `sell_price` decimal(15,2) DEFAULT 0.00,
  `price` decimal(15,2) DEFAULT 0.00, -- Alias used by some code
  `stock_quantity` decimal(15,4) DEFAULT 0.0000,
  `reorder_level` int(11) DEFAULT 10,
  `manage_stock` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text,
  `image` varchar(255),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `inventory_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` decimal(15,4) DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_prod_wh` (`product_id`, `warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `type` enum('Purchase','Sale','Transfer','Adjustment') NOT NULL,
  `quantity` decimal(15,4) NOT NULL, -- Negative for sales
  `reference_id` int(11), -- PO ID or Sale ID
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Procurement (Purchase)
-- --------------------------------------------------------

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(100),
  `phone` varchar(50),
  `email` varchar(100),
  `address` text,
  `tax_number` varchar(50),
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `payable_account_id` int(11), -- Link to GL
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `po_number` varchar(50),
  `status` enum('Draft','Ordered','Received','Cancelled') DEFAULT 'Draft',
  `order_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Sales & POS
-- --------------------------------------------------------

CREATE TABLE `sales_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Confirmed','Invoiced','Cancelled') DEFAULT 'Draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sales_order_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `sales_orders`(`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(100),
  `phone` varchar(50),
  `address` text,
  `tax_number` varchar(50),
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `loyalty_points` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL, -- Cashier
  `invoice_no` varchar(50) NOT NULL,
  `sale_date` datetime NOT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_total` decimal(15,2) DEFAULT 0.00,
  `discount_total` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `payment_method` enum('Cash','Card','Split') DEFAULT 'Cash',
  `status` enum('Completed','Void','Returned') DEFAULT 'Completed',
  `notes` text,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`terminal_id`) REFERENCES `terminals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sale_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `method` varchar(50) NOT NULL, -- Cash, Card, EasyPaisa
  `amount` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Initial Seed Data
-- --------------------------------------------------------

-- Default Company & Store
INSERT INTO `companies` (`company_name`, `fiscal_year_start`) VALUES ('My Retail Company', CURDATE());
INSERT INTO `stores` (`company_id`, `store_name`, `store_code`) VALUES (1, 'Main Store', 'STR-001');
INSERT INTO `warehouses` (`company_id`, `store_id`, `warehouse_name`) VALUES (1, 1, 'Main Store Warehouse');

-- Default Terminal
INSERT INTO `terminals` (`store_id`, `name`) VALUES (1, 'Main Terminal');

-- Default Roles
INSERT INTO `roles` (`role_name`) VALUES ('Admin'), ('Manager'), ('Cashier');

-- Default Admin User (pass: admin123)
-- SHA256 or BCrypt hash should be used in app. For now storing plain for initial test or use simple hash.
-- Using a placeholder hash for 'admin123'
INSERT INTO `users` (`company_id`, `store_id`, `username`, `password_hash`, `role_id`) 
VALUES (1, 1, 'admin', '$2y$10$91copRB7ItRPW4fFTdcdxOdRjjqCEoKvF6Wn9uFF5WuNaJNjihD.m', 1);

-- Default Tax
INSERT INTO `taxes` (`name`, `rate`) VALUES ('None', 0.00), ('GST 17%', 17.00);

-- Default COA (Simplified)
INSERT INTO `chart_of_accounts` (`company_id`, `code`, `name`, `type`, `is_group`) VALUES 
(1, '1000', 'Assets', 'Asset', 1),
(1, '1001', 'Cash on Hand', 'Asset', 0),
(1, '1002', 'Inventory Asset', 'Asset', 0),
(1, '1003', 'Accounts Receivable', 'Asset', 0),
(1, '2000', 'Liabilities', 'Liability', 1),
(1, '2001', 'Accounts Payable', 'Liability', 0),
(1, '3000', 'Equity', 'Equity', 1),
(1, '3001', 'Owner Capital', 'Equity', 0),
(1, '4000', 'Revenue', 'Revenue', 1),
(1, '4001', 'Sales Revenue', 'Revenue', 0),
(1, '5000', 'Expenses', 'Expense', 1),
(1, '5001', 'Cost of Goods Sold', 'Expense', 0);


-- --------------------------------------------------------
-- 8. Sales Invoicing (Added via Automated Accounting)
-- --------------------------------------------------------

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `sales_order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `status` enum('Pending','Paid','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `invoice_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL, -- Product ID
  `quantity` decimal(15,4) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendor Invoices & Payments (Added via Automated Accounting)
CREATE TABLE `vendor_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `bill_number` varchar(50) NOT NULL,
  `bill_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('Pending','Paid','Partial') DEFAULT 'Pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `vendor_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `vendor_invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` varchar(50) NOT NULL,
  `reference` varchar(100),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `customer_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` varchar(50) NOT NULL,
  `reference` varchar(100),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. FBR Integration
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `fbr_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `pos_id` VARCHAR(50),
    `auth_token` VARCHAR(255),
    `base_url` VARCHAR(255) DEFAULT 'https://esp.fbr.gov.pk:8243/FBR/v1/api/Live/PostData',
    `is_active` BOOLEAN DEFAULT 0,
    `environment` ENUM('TEST', 'PRODUCTION') DEFAULT 'PRODUCTION',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `unique_company` (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fbr_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `invoice_no` VARCHAR(50),
    `request_payload` TEXT,
    `response_payload` TEXT,
    `http_status` INT,
    `status` ENUM('PENDING', 'SYNCED', 'FAILED') DEFAULT 'PENDING',
    `error_message` TEXT,
    `synced_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sale` (sale_id),
    INDEX `idx_status` (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alter sales table to include FBR columns (if not exists pattern is harder in SQL file, but for a fresh schema it's fine)
-- Since this is the initial schema, we can add them to the CREATE TABLE statement above if we want, 
-- but adding them as ALTER here for consistency with the setup script.
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `fbr_invoice_no` VARCHAR(50) AFTER `invoice_no`;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `fbr_qr_code` TEXT AFTER `fbr_invoice_no`;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `fbr_status` ENUM('PENDING', 'SYNCED', 'FAILED') DEFAULT 'PENDING' AFTER `fbr_qr_code`;

COMMIT;
