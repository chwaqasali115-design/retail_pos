<?php
// fix_unbalanced_journals.php
require_once 'config/config.php';
require_once 'core/Database.php';
require_once 'core/AccountingHelper.php';

$db = new Database();
$conn = $db->getConnection();
$acc = new AccountingHelper($conn);

echo "Starting Journal Repair...\n";

// 1. Identify Unbalanced Journals
$sql = "
    SELECT 
        j.id, 
        j.reference, 
        SUM(i.debit) as total_debit, 
        SUM(i.credit) as total_credit,
        (SUM(i.debit) - SUM(i.credit)) as diff
    FROM gl_journal j
    JOIN gl_journal_items i ON j.id = i.journal_id
    GROUP BY j.id
    HAVING ABS(diff) > 0.01
";

$stmt = $conn->query($sql);
$badJournals = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($badJournals)) {
    echo "No unbalanced journals found. Everything looks good!\n";
    exit;
}

echo "Found " . count($badJournals) . " unbalanced journals.\n";

$salesAccountId = $acc->getAccountId(4001); // Sales Revenue

$conn->beginTransaction();
try {
    foreach ($badJournals as $j) {
        echo "Fixing Journal ID: {$j['id']} (Ref: {$j['reference']}, Diff: {$j['diff']})...\n";

        // Strategy: 
        // The Debit (Cash) is correct (Matches Grand Total).
        // The Credit (Tax) is likely correct (Calculated from Tax Total).
        // The Credit (Sales) is WRONG (It was using Subtotal inclusive of tax).
        // We need to set Credit (Sales) = Debit (Total) - Credit (Tax).
        // Or simply: Adjust Sales Credit by subtracting the difference from it? 
        // Wait, if diff is negative (Debit < Credit), it means Credit is too high. 
        // We should reduce Credit by abs(diff).

        // Let's rely on re-calculating the balance.
        // Get all items for this journal
        $iStmt = $conn->prepare("SELECT * FROM gl_journal_items WHERE journal_id = ?");
        $iStmt->execute([$j['id']]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalDebit = 0;
        $totalCreditObj = []; // map account_id -> amount

        foreach ($items as $item) {
            $totalDebit += $item['debit'];
            // We want to find the Sales Line to adjust
        }

        // Simpler approach:
        // Update the Sales Revenue Line (4001) for this journal.
        // New Sales Credit = Old Sales Credit + Diff (if diff is negative, it reduces credit).

        // Check if Sales Line exists
        $sCheck = $conn->prepare("SELECT id, credit FROM gl_journal_items WHERE journal_id = ? AND account_id = ?");
        $sCheck->execute([$j['id'], $salesAccountId]);
        $salesLine = $sCheck->fetch(PDO::FETCH_ASSOC);

        if ($salesLine) {
            // Apply diff to this line
            // If Diff is -100 (Credits are 100 higher than Debits), add -100 to Credit => reduces credit.
            $newCredit = $salesLine['credit'] + $j['diff'];

            // Safety check: Credit shouldn't be negative unless it's a return, handling logic:
            if ($newCredit < 0) {
                // This might be a return (Debit Sales). 
                // If original was Credit, and now negative, something is weird.
                // But in our case, we know it's Sales being OVER credited.
            }

            $update = $conn->prepare("UPDATE gl_journal_items SET credit = ? WHERE id = ?");
            $update->execute([$newCredit, $salesLine['id']]);

            echo "  -> Updated Sales Line ID {$salesLine['id']}: Old Credit {$salesLine['credit']} -> New Credit $newCredit\n";
        } else {
            echo "  -> CRITICAL: No Sales Revenue line found for this journal. Skipping automatic fix.\n";
        }
    }

    $conn->commit();
    echo "Repair completed successfully.\n";

} catch (Exception $e) {
    $conn->rollBack();
    echo "Error during repair: " . $e->getMessage();
}
