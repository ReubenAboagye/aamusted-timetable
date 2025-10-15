<?php
// Secret admin-only user creation page (not linked in navigation)
include_once __DIR__ . '/../includes/custom_error_handler.php';
include_once __DIR__ . '/../connect.php';
include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
requireAdmin();
// First-user bootstrap: if no users exist, allow access without login; otherwise require admin
$__allowBootstrap = false;
try {
	$chk = $conn->query('SELECT COUNT(*) AS c FROM users');
	if ($chk) {
		$row = $chk->fetch_assoc();
		$__allowBootstrap = isset($row['c']) && (int)$row['c'] === 0;
	}
} catch (Throwable $e) {
	// If users table missing, we cannot proceed securely; show error
	http_response_code(500);
	echo '<!DOCTYPE html><html><body><pre>Users table not found. Please apply the SQL migration to create the users table.</pre></body></html>';
	exit;
}

if (!$__allowBootstrap) {
	requireAdmin();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validateCSRFToken($token)) {
		$error = 'Invalid request token';
	} else {
		$username = trim((string)($_POST['username'] ?? ''));
		$password = (string)($_POST['password'] ?? '');
		$is_admin = isset($_POST['is_admin']) ? 1 : 0;
		$is_active = isset($_POST['is_active']) ? 1 : 1;

		if ($username === '' || $password === '') {
			$error = 'Username and password are required';
		} elseif (strlen($username) > 100) {
			$error = 'Username must be at most 100 characters';
		} elseif (strlen($password) < 8) {
			$error = 'Password must be at least 8 characters';
		} else {
			// Check uniqueness
			$check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
			if (!$check) {
				$error = 'Database error preparing uniqueness check';
			} else {
				$check->bind_param('s', $username);
				$check->execute();
				$res = $check->get_result();
				$exists = $res && $res->fetch_assoc();
				$check->close();
				if ($exists) {
					$error = 'Username already exists';
				} else {
					$hash = password_hash($password, PASSWORD_BCRYPT);
					$stmt = $conn->prepare('INSERT INTO users (username, password_hash, is_admin, is_active) VALUES (?, ?, ?, ?)');
					if (!$stmt) {
						$error = 'Database error preparing insert';
					} else {
						$stmt->bind_param('ssii', $username, $hash, $is_admin, $is_active);
						$stmt->execute();
						if ($stmt->affected_rows > 0) {
							$success = 'User created successfully';
							// Clear form fields on success
							$username = '';
						}
						$stmt->close();
					}
				}
			}
		}
	}
}

$csrf = getCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Secret: Add User</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
	<style>
		:root { --primary-color:#800020; --hover-color:#600010; --accent-color:#FFD700; }
		body { background: linear-gradient(180deg, rgba(128,0,32,0.06), rgba(128,0,32,0.01)); min-height:100vh; }
		.card { border:none; border-radius:12px; box-shadow: 0 10px 30px rgba(128,0,32,0.12); }
		.form-label { font-weight:600; color:#4a4a4a; }
		.form-control { border:2px solid #eee; border-radius:8px; padding:10px 12px; }
		.form-control:focus { border-color:var(--primary-color); box-shadow:0 0 0 0.2rem rgba(128,0,32,0.15); }
		.btn-primary { background-color:var(--primary-color); border-color:var(--primary-color); font-weight:600; }
		.btn-primary:hover { background-color:var(--hover-color); border-color:var(--hover-color); }
	</style>
</head>
<body>
	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">
    <div class="text-center mb-3">
					<img src="../images/aamustedLog.png" alt="AAMUSTED Logo" style="height:64px;" />
					<h4 class="mt-2" style="color:#800020; font-weight:700;">Admin Tool</h4>
					<p class="text-muted mb-0">Add Application Users</p>
					<?php if ($__allowBootstrap): ?>
						<div class="alert alert-warning mt-3" role="alert">
							<strong>Initial setup:</strong> No users found. Create the first admin account now.
						</div>
					<?php endif; ?>
				</div>
				<div class="card">
					<div class="card-body p-4">
						<?php if (!empty($error)): ?>
							<div class="alert alert-danger d-flex align-items-center" role="alert">
								<i class="fa-solid fa-triangle-exclamation me-2"></i>
								<div><?php echo htmlspecialchars($error); ?></div>
							</div>
						<?php endif; ?>
						<?php if (!empty($success)): ?>
							<div class="alert alert-success d-flex align-items-center" role="alert">
								<i class="fa-solid fa-circle-check me-2"></i>
								<div><?php echo htmlspecialchars($success); ?></div>
							</div>
						<?php endif; ?>
						<form method="post" novalidate>
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>" />
							<div class="mb-3">
								<label class="form-label">Username</label>
								<input class="form-control" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" maxlength="100" required />
							</div>
							<div class="mb-3">
								<label class="form-label">Password</label>
								<input type="password" class="form-control" name="password" minlength="8" required />
								<div class="form-text">Minimum 8 characters. Will be stored as a bcrypt hash.</div>
							</div>
							<div class="form-check form-switch mb-2">
								<input class="form-check-input" type="checkbox" id="is_admin" name="is_admin">
								<label class="form-check-label" for="is_admin">Administrator</label>
							</div>
							<div class="form-check form-switch mb-4">
								<input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
								<label class="form-check-label" for="is_active">Active</label>
							</div>
							<button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Create User</button>
							<a href="../index.php" class="btn btn-outline-secondary ms-2">Back to Dashboard</a>
						</form>
					</div>
				</div>
				<p class="text-center text-muted small mt-3 mb-0">&copy; <?php echo date('Y'); ?> AAMUSTED</p>
			</div>
		</div>
	</div>
</body>
</html>


