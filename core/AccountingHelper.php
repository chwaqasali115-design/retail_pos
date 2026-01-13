<?php
// core/AccountingHelper.php

class AccountingHelper
{
    private $conn;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    public function createJournalEntry($companyId, $date, $ref, $desc, $items)
    {
        // Items = [ ['account_id' => 1001, 'debit' => 500, 'credit' => 0], ... ]

        // 1. Get Fiscal Year
        $fy = $this->conn->query("SELECT id FROM fiscal_years WHERE company_id = $companyId AND '$date' BETWEEN start_date AND end_date LIMIT 1")->fetch();
        $fyId = $fy ? $fy['id'] : 0;
        // If no FY, normally error, but for proto we skip or use default 1
        if (!$fyId)
            $fyId = 1;

        // 2. Insert Header
        $stmt = $this->conn->prepare("INSERT INTO gl_journal (company_id, fiscal_year_id, journal_date, reference, description, posted_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$companyId, $fyId, $date, $ref, $desc, $_SESSION['user_id']]);
        $journalId = $this->conn->lastInsertId();

        // 3. Insert Items
        $ins = $this->conn->prepare("INSERT INTO gl_journal_items (journal_id, account_id, debit, credit) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $ins->execute([$journalId, $item['account_id'], $item['debit'], $item['credit']]);
        }

        return $journalId;
    }
    public function getAccountId($code)
    {
        $stmt = $this->conn->prepare("SELECT id FROM chart_of_accounts WHERE code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ? $row['id'] : null;
    }
}
