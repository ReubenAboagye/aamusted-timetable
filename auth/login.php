<?php
include_once __DIR__ . '/../includes/custom_error_handler.php';
include_once __DIR__ . '/../connect.php';
include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

if (auth_is_enabled() && auth_is_admin_logged_in()) {
    $next = isset($_GET['next']) ? $_GET['next'] : '/index.php';
    header('Location: ' . $next);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validateCSRFToken($token)) {
		$error = 'Invalid request token';
	} else {
		$username = $_POST['username'] ?? '';
		$password = $_POST['password'] ?? '';
		$result = admin_login($conn, (string)$username, (string)$password);
		if ($result['success']) {
			$next = isset($_GET['next']) ? $_GET['next'] : ($_POST['next'] ?? '/index.php');
			header('Location: ' . $next);
			exit;
		} else {
			$error = $result['message'] ?? 'Login failed';
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
	<title>AAMUSTED - Admin Login</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
	<style>
		:root {
			--primary-color: #800020;
			--hover-color: #600010;
			--accent-color: #FFD700;
			--brand-green: #198754;
		}
		body {
			min-height: 100vh;
			background: linear-gradient(180deg, rgba(128,0,32,0.08) 0%, rgba(128,0,32,0.02) 100%);
			background-color: #fff;
			font-family: 'Open Sans', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
		}
		.login-card {
			border: none;
			border-radius: 12px;
			box-shadow: 0 10px 30px rgba(128,0,32,0.12);
		}
		.brand-header {
			text-align: center;
			padding: 24px 16px 8px 16px;
		}
		.brand-logo {
			height: 72px;
			width: auto;
			filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
		}
		.brand-title {
			margin-top: 12px;
			font-weight: 700;
			color: var(--primary-color);
			letter-spacing: 0.3px;
		}
		.form-label { font-weight: 600; color: #4a4a4a; }
		.form-control { padding: 10px 12px; border-radius: 8px; border: 2px solid #eee; }
		.form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(128,0,32,0.15); }
		.btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); font-weight: 600; }
		.btn-primary:hover { background-color: var(--hover-color); border-color: var(--hover-color); }
		.helper-links { display: flex; justify-content: space-between; font-size: 0.9rem; }
		.helper-links a { color: var(--primary-color); text-decoration: none; }
		.helper-links a:hover { color: var(--hover-color); text-decoration: underline; }
		.footer-note { color: #6c757d; }
		.badge-accent { background-color: var(--accent-color); color: #222; font-weight: 700; }
	</style>
</head>
<body>
	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">
				<div class="card login-card">
					<div class="brand-header">
						<img src="../images/aamustedLog.png" alt="AAMUSTED Logo" class="brand-logo" />
						<h5 class="brand-title">AAMUSTED Timetable Generator</h5>
						<span class="badge badge-accent rounded-pill px-3 py-2">Admin Portal</span>
					</div>
					<div class="card-body p-4 pt-3">
						<h6 class="mb-3" style="font-weight:700;color:#333;">Sign in</h6>
						<?php if (!empty($error)): ?>
							<div class="alert alert-danger d-flex align-items-center" role="alert">
								<i class="fa-solid fa-triangle-exclamation me-2"></i>
								<div><?php echo htmlspecialchars($error); ?></div>
							</div>
						<?php endif; ?>
						<form method="post" novalidate>
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>" />
							<input type="hidden" name="next" value="<?php echo htmlspecialchars($_GET['next'] ?? '/index.php'); ?>" />
							<div class="mb-3">
								<label class="form-label">Username</label>
								<input class="form-control" name="username" autocomplete="username" required />
							</div>
							<div class="mb-3">
								<label class="form-label">Password</label>
								<input type="password" class="form-control" name="password" autocomplete="current-password" required />
							</div>
							<button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
						</form>
					</div>
				</div>
				<p class="footer-note small text-center mt-3">&copy; <?php echo date('Y'); ?> AAMUSTED</p>
			</div>
		</div>
	</div>
</body>
</html>


