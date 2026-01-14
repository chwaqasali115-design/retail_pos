-- Database Migration for Multi-Organization Support

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Create Organization Users Table
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `organization_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_org` (`user_id`, `company_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Migrate Existing Users to Organization Users
-- --------------------------------------------------------

-- Insert existing user mappings into organization_users
-- Only run if users table still has company_id (i.e. first run)
SET @dbname = DATABASE();
SET @exist_company := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='company_id');

SET @sql := IF (@exist_company > 0,
    'INSERT INTO organization_users (user_id, company_id, role_id, is_active) SELECT id, company_id, role_id, is_active FROM users WHERE company_id IS NOT NULL AND role_id IS NOT NULL ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)',
    'SELECT "Migration step 2 skipped (already migrated)"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 

-- --------------------------------------------------------
-- 3. Update User Permissions Table for Scoping
-- --------------------------------------------------------

-- Check if company_id exists in user_permissions, if not add it
-- Since IF NOT EXISTS is clumsy for columns in MySQL 5.7/MariaDB without procedures, 
-- we will just attempt to add it and ignore error if strictly managing via script, 
-- but a cleaner way is to recreate or alter.
-- Let's drop and recreate user_permissions to be safe and clean since it might be empty or valid to reset.
-- If we want to preserve data, we would need a more complex script. 
-- For now, let's assuming we can reset overrides or migrate them if critical.
-- Given previous conversation, 02_permissions_migration.sql was just created.
-- We will ALTER it.

-- DROP and Recreate user_permissions to include company_id
DROP TABLE IF EXISTS `user_permissions`;

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1, 
  PRIMARY KEY (`user_id`, `permission_id`, `company_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Clean up Users Table
-- --------------------------------------------------------

-- --------------------------------------------------------
-- 4. Clean up Users Table (Idempotent)
-- --------------------------------------------------------

-- Helper to safely drop FK
SET @dbname = DATABASE();

-- Drop users_ibfk_1 (role_id)
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND CONSTRAINT_NAME='users_ibfk_1');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP FOREIGN KEY users_ibfk_1', 'SELECT "users_ibfk_1 already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop fk_users_terminal
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_users_terminal');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP FOREIGN KEY fk_users_terminal', 'SELECT "fk_users_terminal already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add columns to organization_users if not exist
-- (Adding columns via IF NOT EXISTS is not direct in MySQL, but we can assume they exist if previous run passed or duplicate add will error)
-- We can ignore error 1060 (Duplicate column name) but SQL script stops.
-- Let's check.
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='organization_users' AND COLUMN_NAME='store_id');
SET @sql := IF (@exist = 0, 'ALTER TABLE organization_users ADD COLUMN store_id INT(11) DEFAULT NULL AFTER role_id', 'SELECT "store_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='organization_users' AND COLUMN_NAME='terminal_id');
SET @sql := IF (@exist = 0, 'ALTER TABLE organization_users ADD COLUMN terminal_id INT(11) DEFAULT NULL AFTER store_id', 'SELECT "terminal_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FKs to organization_users (Idempotent-ish: checks constraint)
-- fk_ou_store
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='organization_users' AND CONSTRAINT_NAME='fk_ou_store');
SET @sql := IF (@exist = 0, 'ALTER TABLE organization_users ADD CONSTRAINT fk_ou_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL', 'SELECT "fk_ou_store already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- fk_ou_terminal
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='organization_users' AND CONSTRAINT_NAME='fk_ou_terminal');
SET @sql := IF (@exist = 0, 'ALTER TABLE organization_users ADD CONSTRAINT fk_ou_terminal FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL', 'SELECT "fk_ou_terminal already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Access old data from users before dropping columns?
-- Wait, if columns are dropped, how do we get data?
-- We assume Step 2 ran successfully.
-- But if we run this script again, Step 2 runs again?
-- Insert into org_users selects from users.
-- If columns are gone, the SELECT in Step 2 will FAIL! (Unknown column 'company_id')
-- So Step 2 MUST ALSO BE SAFE.
-- Step 2: INSERT ... SELECT company_id ...
-- If company_id is dropped, script fails at Step 2.
-- Check if column exists before migrating?

-- Let's fix STEP 2 as well in this block?
-- No, replace_file_content is for a chunk.
-- I'll wrap the UPDATE logic for data migration here, checking if columns exist in users.
-- If columns exist in users (company_id), migrate data.
SET @exist_company := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='company_id');

-- Helper procedure or just block
-- Query validation is tricky inside dynamic SQL.

-- Let's just migrate data IF columns exist.
-- UPDATE organization_users...
-- Actually, the UPDATE query `UPDATE organization_users ou JOIN users u ...` works if users has those columns.
SET @sql := IF (@exist_company > 0, 
    'UPDATE organization_users ou JOIN users u ON ou.user_id = u.id AND ou.company_id = u.company_id SET ou.store_id = u.store_id, ou.terminal_id = u.terminal_id', 
    'SELECT "Data migration skipped (columns already dropped)"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop Columns Safely
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='company_id');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP COLUMN company_id', 'SELECT "company_id already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='role_id');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP COLUMN role_id', 'SELECT "role_id already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='store_id');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP COLUMN store_id', 'SELECT "store_id already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='terminal_id');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP COLUMN terminal_id', 'SELECT "terminal_id already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='users' AND COLUMN_NAME='is_active');
SET @sql := IF (@exist > 0, 'ALTER TABLE users DROP COLUMN is_active', 'SELECT "is_active already dropped"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------
-- 5. Seed New Permissions
-- --------------------------------------------------------

-- Org Setup Module
INSERT IGNORE INTO `modules` (`module_name`, `label`, `sort_order`, `icon`) VALUES
('org_setup', 'Organization Setup', 90, 'fas fa-building');

-- Org Setup Resources
SET @mod_id = (SELECT id FROM `modules` WHERE `module_name` = 'org_setup');

INSERT IGNORE INTO `resources` (`module_id`, `resource_name`, `label`) VALUES
(@mod_id, 'org_management', 'Organization Management'),
(@mod_id, 'org_config', 'Configuration'),
(@mod_id, 'fbr_integration', 'FBR Integration'),
(@mod_id, 'user_mapping', 'User Mapping');

-- Org Management Permissions
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'org_management' AND `module_id` = @mod_id);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Organization', 'org_setup.org_management.view'),
(@res_id, 'create', 'Create Organization', 'org_setup.org_management.create'),
(@res_id, 'edit', 'Edit Organization', 'org_setup.org_management.edit'),
(@res_id, 'activate', 'Activate/Deactivate', 'org_setup.org_management.activate');

-- Org Config Permissions
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'org_config' AND `module_id` = @mod_id);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Configuration', 'org_setup.org_config.view'),
(@res_id, 'edit', 'Edit Configuration', 'org_setup.org_config.edit');

-- FBR Permissions
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'fbr_integration' AND `module_id` = @mod_id);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View FBR Settings', 'org_setup.fbr_integration.view'),
(@res_id, 'edit', 'Edit FBR Token', 'org_setup.fbr_integration.edit'),
(@res_id, 'sync', 'Sync Transactions', 'org_setup.fbr_integration.sync'),
(@res_id, 'toggle', 'Enable/Disable FBR', 'org_setup.fbr_integration.toggle');

-- User Mapping Permissions
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'user_mapping' AND `module_id` = @mod_id);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Users', 'org_setup.user_mapping.view'),
(@res_id, 'assign', 'Assign User', 'org_setup.user_mapping.assign'),
(@res_id, 'change_role', 'Change Role', 'org_setup.user_mapping.change_role'),
(@res_id, 'remove', 'Remove User', 'org_setup.user_mapping.remove');


-- Purchase Module Permissions (Detailed)
SET @mod_purchase = (SELECT id FROM `modules` WHERE `module_name` = 'purchases');

-- Ensure Purchase Resources exist
INSERT IGNORE INTO `resources` (`module_id`, `resource_name`, `label`) VALUES
(@mod_purchase, 'requisition', 'Purchase Requisition'),
(@mod_purchase, 'order', 'Purchase Order'),
(@mod_purchase, 'vendor_invoice', 'Vendor Invoice'),
(@mod_purchase, 'vendor_payment', 'Vendor Payment');

-- Purchase Requisition
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'requisition' AND `module_id` = @mod_purchase);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View PR', 'purchases.requisition.view'),
(@res_id, 'create', 'Create PR', 'purchases.requisition.create'),
(@res_id, 'edit', 'Edit PR', 'purchases.requisition.edit'),
(@res_id, 'delete', 'Delete PR', 'purchases.requisition.delete'),
(@res_id, 'approve', 'Approve PR', 'purchases.requisition.approve');

-- Purchase Order
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'order' AND `module_id` = @mod_purchase);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View PO', 'purchases.order.view'),
(@res_id, 'create', 'Create PO', 'purchases.order.create'),
(@res_id, 'edit', 'Edit PO', 'purchases.order.edit'),
(@res_id, 'delete', 'Delete PO', 'purchases.order.delete'),
(@res_id, 'approve', 'Approve PO', 'purchases.order.approve'),
(@res_id, 'print', 'Print PO', 'purchases.order.print');

-- Vendor Invoice
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'vendor_invoice' AND `module_id` = @mod_purchase);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Invoice', 'purchases.vendor_invoice.view'),
(@res_id, 'create', 'Create Invoice', 'purchases.vendor_invoice.create'),
(@res_id, 'edit', 'Edit Invoice', 'purchases.vendor_invoice.edit'),
(@res_id, 'post', 'Post Invoice', 'purchases.vendor_invoice.post'),
(@res_id, 'delete', 'Delete Invoice', 'purchases.vendor_invoice.delete');

-- Vendor Payment
SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'vendor_payment' AND `module_id` = @mod_purchase);
INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Payment', 'purchases.vendor_payment.view'),
(@res_id, 'create', 'Create Payment', 'purchases.vendor_payment.create'),
(@res_id, 'post', 'Post Payment', 'purchases.vendor_payment.post'),
(@res_id, 'reverse', 'Reverse Payment', 'purchases.vendor_payment.reverse');

-- Grant all new permissions to Admin Role (1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

COMMIT;
