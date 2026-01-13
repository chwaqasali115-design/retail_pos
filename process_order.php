<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start a transaction (D365 style: all or nothing)
        $conn->beginTransaction();

        // 1. Collect Header Data
        $order_number = $_POST['order_number'];
        $customer_id  = $_POST['customer_id'];
        $order_date   = $_POST['order_date'];
        $total_amount = $_POST['total_amount'];
        $status       = 'Confirmed'; // Changing status from Draft to Confirmed

        // 2. Insert into sales_orders (The Header)
        $stmt = $conn->prepare("INSERT INTO sales_orders (order_number, customer_id, order_date, total_amount, status) 
                                VALUES (:ord_no, :cust_id, :ord_date, :total, :status)");
        $stmt->execute([
            ':ord_no'   => $order_number,
            ':cust_id'  => $customer_id,
            ':ord_date' => $order_date,
            ':total'    => $total_amount,
            ':status'   => $status
        ]);
        
        $order_id = $conn->lastInsertId();

        // 3. Insert into sales_order_lines (The Lines)
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $lineStmt = $conn->prepare("INSERT INTO sales_order_lines (order_id, product_id, quantity, unit_price, line_total) 
                                        VALUES (:oid, :pid, :qty, :price, :ltotal)");
            
            $stockStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :pid");

            foreach ($_POST['items'] as $item) {
                $pid   = $item['product_id'];
                $qty   = $item['qty'];
                $price = $item['price'];
                $ltotal = $qty * $price;

                // Insert the line
                $lineStmt->execute([
                    ':oid'    => $order_id,
                    ':pid'    => $pid,
                    ':qty'    => $qty,
                    ':price'  => $price,
                    ':ltotal' => $ltotal
                ]);

                // Update Stock
                $stockStmt->execute([
                    ':qty' => $qty,
                    ':pid' => $pid
                ]);
            }
        }

        // Commit the transaction
        $conn->commit();

        // Redirect to a success page or back to order list
        header("Location: sales_orders_list.php?success=Order " . $order_number . " confirmed");
        exit();

    } catch (Exception $e) {
        // If anything fails, undo everything
        $conn->rollBack();
        echo "Error creating Sales Order: " . $e->getMessage();
    }
} else {
    header("Location: create_sales_order.php");
}