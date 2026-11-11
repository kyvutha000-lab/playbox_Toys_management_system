<?php
/**
 * php/login.php - Handles login, logout, and "who am I" checks.
 * Actions (via ?action=): login (POST), logout (POST), me (GET)
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'login';

switch ($action) {

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST required', 405);
        $input = get_input();
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if ($username === '' || $password === '') {
            json_error('Username and password are required.');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            json_error('Invalid username or password.', 401);
        }

        if ($user['status'] !== 'Active') {
            json_error('This account is inactive. Contact your administrator.', 403);
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];

        json_response([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ]
        ]);
        break;

    case 'logout':
        session_unset();
        session_destroy();
        json_response(['success' => true, 'message' => 'Logged out']);
        break;

    case 'me':
        if (empty($_SESSION['user_id'])) {
            json_response(['success' => false, 'logged_in' => false]);
        }
        json_response([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'email' => $_SESSION['email'] ?? '',
                'role' => $_SESSION['role'],
            ]
        ]);
        break;

    case 'update_profile':
        require_login();
        $input = get_input();
        $fullName = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        if ($fullName === '') json_error('Full name is required');

        $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?")
            ->execute([$fullName, $email, $_SESSION['user_id']]);

        $_SESSION['full_name'] = $fullName;

        json_response(['success' => true, 'message' => 'Profile updated', 'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $fullName,
            'role' => $_SESSION['role'],
        ]]);
        break;

    case 'change_password':
        require_login();
        $input = get_input();
        $old = $input['old_password'] ?? '';
        $new = $input['new_password'] ?? '';
        if (strlen($new) < 6) json_error('New password must be at least 6 characters.');

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($old, $row['password'])) {
            json_error('Old password is incorrect.', 401);
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $upd->execute([$hash, $_SESSION['user_id']]);

        json_response(['success' => true, 'message' => 'Password updated.']);
        break;

    default:
        json_error('Unknown action', 404);
}
