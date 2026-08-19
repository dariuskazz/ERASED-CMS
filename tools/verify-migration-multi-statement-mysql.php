<?php
declare(strict_types=1);

/**
 * Empirical check (Phase 0 of the core-update plan): does PDO::exec() on the
 * MySQL/MariaDB driver, with this codebase's exact connection flags (no
 * PDO::MYSQL_ATTR_MULTI_STATEMENTS), reliably run every statement in a
 * semicolon-separated multi-statement string, or does it silently run only
 * the first one / throw?
 *
 * Not part of tools/run-tests.php's standalone suite - requires a live
 * MySQL/MariaDB connection. Run manually against the podman `db` service.
 */

$config = require dirname(__DIR__).'/storage/config.php';
$c = $config['db'];

$pdo = new PDO(
    "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
    $c['user'],
    $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

$table = 'zzz_multi_stmt_test_'.bin2hex(random_bytes(4));

echo "Using scratch table: {$table}\n";

$sql = "CREATE TABLE {$table} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, val VARCHAR(20) NOT NULL);\n"
    ."INSERT INTO {$table} (val) VALUES ('one');\n"
    ."INSERT INTO {$table} (val) VALUES ('two');\n"
    ."INSERT INTO {$table} (val) VALUES ('three');\n";

$exception = null;
try {
    $pdo->exec($sql);
} catch (Throwable $e) {
    $exception = $e;
}

$tableExists = false;
$rowCount = 0;
try {
    $tableExists = (bool)$pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
    if ($tableExists) {
        $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
} catch (Throwable $e) {
    // table genuinely doesn't exist
}

echo "Exception thrown by exec(): ".($exception ? get_class($exception).' - '.$exception->getMessage() : 'none')."\n";
echo "Table exists after exec(): ".($tableExists ? 'yes' : 'no')."\n";
echo "Row count after exec(): {$rowCount} (expected 3 if all statements ran)\n";

if ($tableExists) {
    $pdo->exec("DROP TABLE {$table}");
    echo "Scratch table dropped.\n";
}

echo "\n=== VERDICT ===\n";
if ($tableExists && $rowCount === 3) {
    echo "PDO::exec() ran ALL statements reliably in this environment. Bug 2 fix (statement splitting) is NOT required.\n";
} elseif ($tableExists && $rowCount < 3) {
    echo "PDO::exec() ran the CREATE TABLE but only {$rowCount}/3 INSERTs. Multi-statement exec() is UNRELIABLE here. Bug 2 fix IS required.\n";
} else {
    echo "PDO::exec() did not even create the table (or threw). Multi-statement exec() is UNRELIABLE here. Bug 2 fix IS required.\n";
}
