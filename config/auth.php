<?php
// config/auth.php - Authentication and Session Management

require_once __DIR__ . '/db.php';

function startAppSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function getCurrentUser(): ?array {
    startAppSession();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function requireAuth(?array $allowedRoles = null): array {
    $user = getCurrentUser();
    if (!$user) {
        header("Location: login.php");
        exit;
    }

    if ($allowedRoles !== null && !in_array($user['role'], $allowedRoles, true)) {
        header("Location: dashboard.php?error=unauthorized");
        exit;
    }

    return $user;
}

function loginUser(string $identifier, string $password): bool {
    startAppSession();
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :id OR nik = :id");
    $stmt->execute([':id' => trim($identifier)]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    // Check hashed password or demo fallback
    if (password_verify($password, $user['password']) || $password === '123456') {
        $_SESSION['user_id'] = $user['id'];
        return true;
    }

    return false;
}

function quickDemoLogin(int $userId): bool {
    startAppSession();
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        return true;
    }

    return false;
}

function logoutUser(): void {
    startAppSession();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
