# Retail POS Setup Guide

## Requirements
- XAMPP or WAMP Server (PHP 7.4+, MySQL 5.7+).

## Installation

1. **Database Setup**
   - Open `http://localhost/phpmyadmin`
   - Create a database named `retail_pos_db`.
   - Import the file `database/schema.sql`.

2. **Project Setup**
   - Move the `retail_pos` folder to your `htdocs` (XAMPP) or `www` (WAMP) folder.
   - Example path: `C:\xampp\htdocs\retail_pos`.

3. **Check Config**
   - Open `config/config.php`.
   - Ensure DB credentials match your local setup (user: `root`, pass: `` usually).

4. **Login**
   - Open `http://localhost/retail_pos/` in your browser.
   - Use Default Credentials:
     - **User**: `admin`
     - **Pass**: `admin123`

## Features Overview
- **Company Setup**: Add additional stores under Organization.
- **Inventory**: Add products with Cost/Sell price and Tax rules.
- **Purchases**: Create POs for Vendors to increase stock.
- **POS**: Go to POS Terminal to make sales. Stock will deduce automatically.
- **Accounting**: Check Trial Balance to see financial impact.
