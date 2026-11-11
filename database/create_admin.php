<?php
/**
 * database/create_admin.php
 * Run this ONCE in your browser (e.g. http://localhost/PLAYBOX/database/create_admin.php)
 * to (re)create the default admin login with a correctly hashed password.
 * Login afterwards with: username = admin / password = admin123
 * DELETE THIS FILE after use for security.
 */
require_once __DIR__ . '/../php/db.php';

$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare("UPDATE users SET password = ?, status='Active' WHERE username = ?")
        ->execute([$hash, $username]);
    echo "Admin password reset. Login with admin / admin123";
} else {
    $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?,?,?,?,?)")
        ->execute([$username, $hash, 'Alex Admin', 'admin@playbox.com', 'Admin']);
    echo "Admin account created. Login with admin / admin123";
}
