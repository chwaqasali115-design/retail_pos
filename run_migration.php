<?php
require_once 'config/config.php';
require_once 'core/Database.php';

echo "Starting Migration...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sqlFile = 'database/permissions_migration.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found at $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    // Split by ; to run multiple queries if PDO doesn't support multiple queries in one go (some drivers don't)
    // However, most modern mysql drivers do. But safe splitting is often better for error reporting.
    // The migration script uses DELIMITER free syntax, so splitting by ';' might be okay unless there are ; inside strings.
    // Given the simple nature of the SQL, we can try running it as a block or splitting.
    // Let's try running distinct commands.

    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0); // Important for multiquery if needed, but let's try raw exec

    // We can just use exec() for the whole block in many cases with MySQL
    $conn->exec($sql);

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>