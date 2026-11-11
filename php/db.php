<?php
/**
 * db.php - Central database connection for PLAYBOX Toy Store Management System
 * Uses PDO with MySQL.
 */

// Start session for every API request that needs auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database configuration (edit to match your environment) ----
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'playbox');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');

// ---- Response helpers ----
header('Content-Type: application/json; charset=utf-8');

function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function json_error($message, $status = 400) {
    json_response(['success' => false, 'message' => $message], $status);
}

// ---- PDO connection ----
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    json_error('Database connection failed: ' . $e->getMessage(), 500);
}

// ---- Auth guard helper (call from any protected API file) ----
function require_login() {
    if (empty($_SESSION['user_id'])) {
        json_error('Unauthorized. Please log in.', 401);
    }
}

function require_role($roles = []) {
    require_login();
    if (!empty($roles) && !in_array($_SESSION['role'], $roles, true)) {
        json_error('Forbidden. You do not have permission to perform this action.', 403);
    }
}

// ---- Small utility: read JSON body or fall back to $_POST ----
function get_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        return $data;
    }
    return $_POST;
}
