<?php
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}
function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}
function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}
function currentUsername(): string {
    return $_SESSION['username'] ?? '';
}
