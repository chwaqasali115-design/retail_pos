-- Database Migration for Granular Permissions

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Drop existing simplified tables if they collide (Optional, be careful)
-- We will alter/extend instead of dropping to preserve data if possible.
-- However, the requirement implies a fresh structure for permissions.
-- Existing 'role_permissions' and 'permissions' are very basic.
-- We will DROP them for this upgrade as per plan (assuming fresh implementation of this feature).
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `resources`;
DROP TABLE IF EXISTS `modules`;

-- --------------------------------------------------------
-- 2. New Table Structure
-- --------------------------------------------------------

-- Modules: High level grouping (e.g. Sales, Purchasing, Inventory)
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(50) NOT NULL UNIQUE, -- e.g. 'sales'
  `label` varchar(100) NOT NULL, -- e.g. 'Sales Module'
  `sort_order` int(11) DEFAULT 0,
  `icon` varchar(50) DEFAULT 'fas fa-cube',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resources: Specific Forms/Pages within a module (e.g. Invoices, Customers)
CREATE TABLE `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_id` int(11) NOT NULL,
  `resource_name` varchar(50) NOT NULL, -- e.g. 'invoices'
  `label` varchar(100) NOT NULL, -- e.g. 'Manage Invoices'
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_mod_res` (`module_id`, `resource_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions: Specific actions on a resource (e.g. create, edit, delete, view, post)
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL, -- e.g. 'create', 'view'
  `label` varchar(100) NOT NULL, -- e.g. 'Create Invoice'
  `slug` varchar(150) NOT NULL UNIQUE, -- e.g. 'sales.invoices.create'
  PRIMARY KEY (`id`),
  FOREIGN KEY (`resource_id`) REFERENCES `resources`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Role Permissions: Mapping Roles to Permissions
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Permissions: User specific overrides
-- is_allowed: 1 = Grant (Override Deny), 0 = Revoke (Override Grant)
-- This allows granular exceptions.
CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1, 
  PRIMARY KEY (`user_id`, `permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Seed Data
-- --------------------------------------------------------

-- Seed Modules
INSERT INTO `modules` (`module_name`, `label`, `sort_order`, `icon`) VALUES
('admin', 'Administration', 99, 'fas fa-cogs'),
('sales', 'Sales & POS', 1, 'fas fa-shopping-cart'),
('purchases', 'Purchases', 2, 'fas fa-truck'),
('inventory', 'Inventory', 3, 'fas fa-boxes'),
('accounting', 'Accounting', 4, 'fas fa-calculator'),
('reports', 'Reports', 5, 'fas fa-chart-line');

-- Seed Resources & Permissions (Helper Procedure not available in simple SQL script usually, doing manually)

-- --- ADMIN ---
-- Users
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) SELECT id, 'users', 'Users & Roles' FROM `modules` WHERE `module_name` = 'admin';
SET @res_id = LAST_INSERT_ID();
INSERT INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Users', 'admin.users.view'),
(@res_id, 'create', 'Create User', 'admin.users.create'),
(@res_id, 'edit', 'Edit User', 'admin.users.edit'),
(@res_id, 'delete', 'Delete User', 'admin.users.delete'),
(@res_id, 'manage_roles', 'Manage Roles', 'admin.users.manage_roles');

-- Settings
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) SELECT id, 'settings', 'System Settings' FROM `modules` WHERE `module_name` = 'admin';
SET @res_id = LAST_INSERT_ID();
INSERT INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Settings', 'admin.settings.view'),
(@res_id, 'edit', 'Edit Settings', 'admin.settings.edit');

-- --- SALES ---
-- POS
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) SELECT id, 'pos', 'POS Terminal' FROM `modules` WHERE `module_name` = 'sales';
SET @res_id = LAST_INSERT_ID();
INSERT INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'access', 'Access POS', 'sales.pos.access'),
(@res_id, 'discount', 'Give Discount', 'sales.pos.discount'),
(@res_id, 'access_all_terminal', 'Access All Terminals', 'sales.pos.access_all_terminal');

-- Invoices
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) SELECT id, 'invoices', 'Sales Invoices' FROM `modules` WHERE `module_name` = 'sales';
SET @res_id = LAST_INSERT_ID();
INSERT INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Invoices', 'sales.invoices.view'),
(@res_id, 'create', 'Create Invoice', 'sales.invoices.create'),
(@res_id, 'edit', 'Edit Invoice', 'sales.invoices.edit'),
(@res_id, 'delete', 'Delete Invoice', 'sales.invoices.delete'),
(@res_id, 'print', 'Print Invoice', 'sales.invoices.print');

-- --- INVENTORY ---
-- Products
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) SELECT id, 'products', 'Products' FROM `modules` WHERE `module_name` = 'inventory';
SET @res_id = LAST_INSERT_ID();
INSERT INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view', 'View Products', 'inventory.products.view'),
(@res_id, 'create', 'Create Product', 'inventory.products.create'),
(@res_id, 'edit', 'Edit Product', 'inventory.products.edit'),
(@res_id, 'delete', 'Delete Product', 'inventory.products.delete'),
(@res_id, 'import', 'Import Products', 'inventory.products.import');

-- --- REPORTS ---
-- Financial Reports
INSERT INTO `resources` (`module_id`, `resource_name`, `label`) SELECT id, 'financial', 'Financial Reports' FROM `modules` WHERE `module_name` = 'reports';
SET @res_id = LAST_INSERT_ID();
INSERT INTO `permissions` (`resource_id`, `action`, `label`, `slug`) VALUES
(@res_id, 'view_pl', 'View Profit & Loss', 'reports.financial.view_pl'),
(@res_id, 'view_tb', 'View Trial Balance', 'reports.financial.view_tb');

-- --------------------------------------------------------
-- 4. Default Permissions for Admin Role
-- --------------------------------------------------------
-- Grant ALL permissions to Admin (Role ID 1)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

COMMIT;
