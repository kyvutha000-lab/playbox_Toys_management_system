<?php
/**
 * php/backup.php - Database Backup & Restore (pure PHP, no mysqldump/exec needed)
 * GET  ?action=export   -> streams a full .sql dump (structure + data) for download
 * POST ?action=restore  -> accepts an uploaded .sql file and re-executes it
 */
require_once __DIR__ . '/db.php';
require_role(['Admin']);

$action = $_GET['action'] ?? null;

if ($action === 'export') {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="playbox_backup_' . date('Y-m-d_His') . '.sql"');

    echo "-- PLAYBOX database backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $createRow['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`$c`", array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            echo "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No valid .sql file was uploaded');
    }

    $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
    if ($sql === false || trim($sql) === '') {
        json_error('The uploaded file is empty or unreadable');
    }

    // Split on semicolons that end a statement (naive but works for our own exports,
    // since PDO::quote() escapes embedded quotes/semicolons inside string values).
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $count = 0;
    try {
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            $pdo->exec($stmt);
            $count++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        json_response(['success' => true, 'message' => "Restore complete ($count statements executed)"]);
    } catch (Exception $e) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        json_error('Restore failed partway through: ' . $e->getMessage() . '. Your database may be partially restored — restore a fresh backup to recover.', 500);
    }
}

json_error('Unsupported request', 405);
