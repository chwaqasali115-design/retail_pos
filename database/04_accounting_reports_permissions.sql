-- Add Permissions for Accounting and Reports

START TRANSACTION;

-- --------------------------------------------------------
-- Accounting Module Permissions
-- --------------------------------------------------------

-- 1. Chart of Accounts
-- Check if resource exists or insert it
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) 
SELECT id, 'coa', 'Chart of Accounts' FROM `modules` WHERE `module_name` = 'accounting'
AND NOT EXISTS (SELECT 1 FROM `resources` WHERE `resource_name` = 'coa' AND `module_id` = modules.id);

SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'coa' LIMIT 1);

INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Chart of Accounts', 'accounting.coa.view'),
(@res_id, 'create', 'Create Account', 'accounting.coa.create'),
(@res_id, 'edit', 'Edit Account', 'accounting.coa.edit');

-- 2. Journal Entry
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) 
SELECT id, 'journal', 'Journal Entries' FROM `modules` WHERE `module_name` = 'accounting'
AND NOT EXISTS (SELECT 1 FROM `resources` WHERE `resource_name` = 'journal' AND `module_id` = modules.id);

SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'journal' LIMIT 1);

INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Journal Entries', 'accounting.journal.view'),
(@res_id, 'create', 'Create Journal Entry', 'accounting.journal.create'),
(@res_id, 'edit', 'Edit Journal Entry', 'accounting.journal.edit');

-- 3. Fiscal Years
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) 
SELECT id, 'fiscal_year', 'Fiscal Years' FROM `modules` WHERE `module_name` = 'accounting'
AND NOT EXISTS (SELECT 1 FROM `resources` WHERE `resource_name` = 'fiscal_year' AND `module_id` = modules.id);

SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'fiscal_year' LIMIT 1);

INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Fiscal Years', 'accounting.fiscal_year.view'),
(@res_id, 'create', 'Create Fiscal Year', 'accounting.fiscal_year.create'),
(@res_id, 'close', 'Close Fiscal Year', 'accounting.fiscal_year.close');

-- --------------------------------------------------------
-- Report Module Permissions
-- --------------------------------------------------------

-- 1. Sales Reports
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) 
SELECT id, 'sales_reports', 'Sales Reports' FROM `modules` WHERE `module_name` = 'reports'
AND NOT EXISTS (SELECT 1 FROM `resources` WHERE `resource_name` = 'sales_reports' AND `module_id` = modules.id);

SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'sales_reports' LIMIT 1);

INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Sales Reports', 'reports.sales.view');

-- 2. Stock Reports
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) 
SELECT id, 'stock_reports', 'Stock Reports' FROM `modules` WHERE `module_name` = 'reports'
AND NOT EXISTS (SELECT 1 FROM `resources` WHERE `resource_name` = 'stock_reports' AND `module_id` = modules.id);

SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'stock_reports' LIMIT 1);

INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Stock Reports', 'reports.stock.view');

-- 3. Purchase Reports
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) 
SELECT id, 'purchase_reports', 'Purchase Reports' FROM `modules` WHERE `module_name` = 'reports'
AND NOT EXISTS (SELECT 1 FROM `resources` WHERE `resource_name` = 'purchase_reports' AND `module_id` = modules.id);

SET @res_id = (SELECT id FROM `resources` WHERE `resource_name` = 'purchase_reports' LIMIT 1);

INSERT IGNORE INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Purchase Reports', 'reports.purchases.view');


-- --------------------------------------------------------
-- Assign Permissions to Admin Role (ID 1)
-- --------------------------------------------------------
-- We use IGNORE to avoid duplicates if re-ran
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

COMMIT;
