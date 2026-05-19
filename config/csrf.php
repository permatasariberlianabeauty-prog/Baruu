<?php
/**
 * NOXARA - CSRF Protection
 */

function generateCsrfToken(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function getCsrfToken(): string
{
    return generateCsrfToken();
}

function verifyCsrfToken(string $token): bool
{
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (empty($sessionToken) || empty($token)) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function validateCsrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(419);
        if (isXhrRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid. Refresh halaman dan coba lagi.']);
        } else {
            setFlash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
        }
        exit;
    }

    // Regenerate after use (double-submit protection)
    unset($_SESSION[CSRF_TOKEN_NAME]);
    generateCsrfToken();
}

function csrfField(): string
{
    $token = getCsrfToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrfMeta(): string
{
    $token = getCsrfToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function isXhrRequest(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
