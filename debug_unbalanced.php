<?php
require_once 'config/config.php';
require_once 'core/Database.php';
$db = new Database();
$conn = $db->getConnection();

echo "Checking for unbalanced journals...\n";

$sql = "
    SELECT 
        j.id, 
        j.reference, 
        j.description, 
        SUM(i.debit) as total_debit, 
        SUM(i.credit) as total_credit,
        (SUM(i.debit) - SUM(i.credit)) as diff
    FROM gl_journal j
    JOIN gl_journal_items i ON j.id = i.journal_id
    GROUP BY j.id
    HAVING ABS(diff) > 0.01
";

$stmt = $conn->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "No unbalanced journals found.\n";
} else {
    foreach ($rows as $row) {
        echo "Journal ID: {$row['id']} | Ref: {$row['reference']} | Desc: {$row['description']}\n";
        echo "Debit: {$row['total_debit']} | Credit: {$row['total_credit']} | Diff: {$row['diff']}\n";
        echo "--------------------------------------------------\n";
    }
}
