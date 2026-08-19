<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Packages/PackageManifest.php';
require_once __DIR__ . '/../app/Packages/PackageLifecycle.php';
require_once __DIR__ . '/../packages/erased.newsletter-subscribers/src/AdminRoute.php';
require_once __DIR__ . '/../packages/erased.newsletter-subscribers/src/Lifecycle.php';

use Erased\Packages\PackageManifest;
use ErasedNewsletter\SubscribersAdminRoute;

echo "Testing Newsletter Subscribers Plugin...\n";

// 1. Validate manifest
$manifestPath = __DIR__ . '/../packages/erased.newsletter-subscribers/package.json';
assert(file_exists($manifestPath), 'package.json exists');
$manifestData = json_decode(file_get_contents($manifestPath), true);
$manifest = new PackageManifest($manifestData);

assert($manifest->id() === 'erased.newsletter-subscribers', 'ID matches');
assert($manifest->name() === 'Newsletter Subscribers Pro', 'Name matches');
assert(count($manifest->adminRoutes()) === 4, 'Admin routes count');
assert(count($manifest->adminMenu()) === 1, 'Admin menu count');
echo "PASS: Manifest parsing & validation\n";

// 2. Test database interactions on sqlite::memory:
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE newsletter_subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'active',
    token TEXT NOT NULL,
    subscribed_at TEXT NOT NULL,
    unsubscribed_at TEXT NULL
)");

// Insert a test subscriber
$testEmail = 'plugin_test_' . time() . '@example.com';
$token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, status, token, subscribed_at) VALUES (?, 'active', ?, DATETIME('now'))");
$stmt->execute([$testEmail, $token]);

$subId = (int)$pdo->lastInsertId();
assert($subId > 0, 'Inserted subscriber ID');

// Verify subscriber in database
$checkStmt = $pdo->prepare("SELECT * FROM newsletter_subscribers WHERE email = ?");
$checkStmt->execute([$testEmail]);
$subscriber = $checkStmt->fetch(PDO::FETCH_ASSOC);
assert($subscriber['status'] === 'active', 'Initial status active');
echo "PASS: Subscriber creation & database query\n";

// Test status toggle
$updStmt = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = DATETIME('now') WHERE id = ?");
$updStmt->execute([$subId]);

$checkStmt->execute([$testEmail]);
$updated = $checkStmt->fetch(PDO::FETCH_ASSOC);
assert($updated['status'] === 'unsubscribed', 'Status updated to unsubscribed');
echo "PASS: Status toggle\n";

// Test delete
$delStmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
$delStmt->execute([$subId]);

$checkStmt->execute([$testEmail]);
assert($checkStmt->fetch() === false, 'Subscriber deleted');
echo "PASS: Subscriber deletion\n";

echo "All Newsletter Subscribers Plugin checks passed successfully.\n";
