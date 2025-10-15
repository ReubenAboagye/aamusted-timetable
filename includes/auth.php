<?php
// Minimal admin-only authentication helpers
// Usage: include this file early (e.g., from includes/header.php) and call requireAdmin()

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

// Lazy include DB connection when needed
if (!isset($conn) || $conn === null) {
	$__connPath = __DIR__ . '/../connect.php';
	if (file_exists($__connPath)) {
		include_once $__connPath;
	}
}

// Feature flag to temporarily disable auth (e.g., during local debug)
// Set AUTH_ENABLED=true in .env to enforce auth; default to true for safety
function auth_is_enabled(): bool {
	$val = getenv('AUTH_ENABLED');
	if ($val === false || $val === '') return true;
	$val = strtolower(trim($val));
	return $val !== 'false' && $val !== '0' && $val !== 'off';
}

// Compute project base path (e.g., "/timetable") using folder name
function auth_base_path(): string {
    // Allow explicit override for environments where auto-detection is unreliable
    $env = getenv('APP_BASE_PATH');
    if ($env !== false && $env !== '') {
        $env = str_replace('\\', '/', trim($env));
        if ($env === '/') return '/';
        return '/' . trim($env, '/');
    }

    // Derive base path dynamically so localhost subfolders like "/timetable" work.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = $dir === '.' ? '/' : $dir;
    $trimmed = trim($dir, '/');
    if ($trimmed === '') {
        return '';
    }
    // Use the first path segment as the app base (e.g., "/timetable")
    $first = explode('/', $trimmed, 2)[0];
    return '/' . $first;
}

function auth_normalize_path(string $path): string {
    // Collapse duplicate slashes and ensure leading slash if non-empty
    $normalized = preg_replace('#/+#', '/', str_replace('\\', '/', $path));
    if ($normalized === null) {
        return $path;
    }
    // Keep query string and fragment intact
    $parts = parse_url($normalized);
    $p = isset($parts['path']) ? $parts['path'] : '';
    if ($p !== '' && $p[0] !== '/') {
        $p = '/' . $p;
    }
    $q = isset($parts['query']) ? '?' . $parts['query'] : '';
    $f = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return ($p === '' ? '/' : $p) . $q . $f;
}

function auth_is_safe_next(string $next): bool {
    // Reject absolute URLs with scheme to avoid open redirects
    return !preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $next);
}

// NOTE: All schema creation and seeding must be done via migrations, not at runtime, for security.

function auth_is_admin_logged_in(): bool {
	return isset($_SESSION['admin_user_id']) && (int)($_SESSION['admin_user_id']) > 0;
}

function auth_current_username(): string {
	return isset($_SESSION['admin_username']) ? (string)$_SESSION['admin_username'] : '';
}

function requireAdmin(): void {
    if (!auth_is_enabled()) {
        return; // auth disabled
    }

    $is_logged_in = auth_is_admin_logged_in();
    $timeout = 300; // 5 minutes

    if ($is_logged_in && isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > $timeout) {
            admin_logout(); // Session expired
            $is_logged_in = false;
        }
    }

    if ($is_logged_in) {
        $_SESSION['last_activity'] = time(); // Update last activity time
    } else {
        // Redirect to login, preserving intended URL
		$current = $_SERVER['REQUEST_URI'] ?? 'index.php';
        if ($current === '' || $current === '/') { $current = 'index.php'; }
        $target = urlencode($current);
        $loginUrl = rtrim(auth_base_path(), '/') . '/auth/login.php?next=' . $target;
		header('Location: ' . $loginUrl);
        exit;
    }
}

function admin_login(mysqli $conn, string $username, string $password): array {
	$username = trim($username);
	if ($username === '' || $password === '') {
		return ['success' => false, 'message' => 'Username and password are required'];
	}

	$sql = "SELECT id, username, password_hash, is_admin, is_active FROM users WHERE username = ? LIMIT 1";
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		return ['success' => false, 'message' => 'Unable to prepare authentication statement'];
	}
	$stmt->bind_param('s', $username);
	$stmt->execute();
	$res = $stmt->get_result();
	$user = $res ? $res->fetch_assoc() : null;
	$stmt->close();
	if (!$user || (int)$user['is_active'] !== 1 || (int)$user['is_admin'] !== 1) {
		return ['success' => false, 'message' => 'Invalid credentials'];
	}
	if (!password_verify($password, (string)$user['password_hash'])) {
		return ['success' => false, 'message' => 'Invalid credentials'];
	}
	// Success — set session
	$_SESSION['admin_user_id'] = (int)$user['id'];
	$_SESSION['admin_username'] = (string)$user['username'];
	$_SESSION['is_admin'] = 1;
    $_SESSION['last_activity'] = time(); // Set initial activity time
	return ['success' => true, 'message' => 'Authenticated'];
}

function admin_logout(): void {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}


