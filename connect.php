<?php
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env if present
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

// Helpers to read env from getenv/$_ENV/$_SERVER to support Dotenv without putenv
function env_get_value(string $key) {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return null;
}

function env_first(array $keys, $default = '') {
    foreach ($keys as $key) {
        $value = env_get_value($key);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }
    return $default;
}

$dbHost = env_first(['DB_HOST', 'MYSQL_HOST'], '');
$dbPort = env_first(['DB_PORT', 'MYSQL_PORT'], '');
$dbName = env_first(['DB_NAME', 'MYSQL_DATABASE'], '');
$dbUser = env_first(['DB_USER', 'MYSQL_USER'], '');
$dbPass = env_first(['DB_PASSWORD', 'MYSQL_PASSWORD'], '');

// Fail fast if required values are missing
$missing = [];
if ($dbHost === '') { $missing[] = 'DB_HOST/MYSQL_HOST'; }
if ($dbPort === '') { $missing[] = 'DB_PORT/MYSQL_PORT'; }
if ($dbName === '') { $missing[] = 'DB_NAME/MYSQL_DATABASE'; }
if ($dbUser === '') { $missing[] = 'DB_USER/MYSQL_USER'; }
if (!empty($missing)) {
    die('Connection failed: missing required env vars: ' . implode(', ', $missing));
}

// Optional SSL for Aiven or other managed MySQL providers
$inlineCa = env_first(['MYSQL_CA_CERT', 'DB_SSL_CA'], '');
$dbSslCaPath = env_first(['DB_SSL_CA_PATH', 'MYSQL_SSL_CA_PATH'], '');
$dbSslEnableFlag = strtolower((string)env_first(['DB_SSL_ENABLE', 'MYSQL_SSL_ENABLE'], '')) === 'true';

// If inline CA is provided, materialize it to a temp file and use it
if ($dbSslCaPath === '' && $inlineCa !== '') {
    $pem = strpos($inlineCa, '-----BEGIN') !== false
        ? $inlineCa
        : ("-----BEGIN CERTIFICATE-----\n" . chunk_split(trim($inlineCa), 64, "\n") . "-----END CERTIFICATE-----\n");
    $tempCaPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('aiven_ca_' . md5($pem) . '.pem');
    if (!file_exists($tempCaPath)) {
        @file_put_contents($tempCaPath, $pem);
    }
    if (file_exists($tempCaPath)) {
        $dbSslCaPath = $tempCaPath;
    }
}

// Enable SSL if explicitly requested or if a CA is available
$dbSslEnable = $dbSslEnableFlag || $dbSslCaPath !== '' || $inlineCa !== '';

// Create connection
$conn = mysqli_init();

if ($dbSslEnable) {
    // If CA path is provided, set it; otherwise rely on system CAs
    if (!empty($dbSslCaPath)) {
        mysqli_ssl_set($conn, null, null, $dbSslCaPath, null, null);
    } else {
        // Aiven accepts empty CA to use system store on many platforms
        mysqli_ssl_set($conn, null, null, null, null, null);
    }
}

// Set MYSQLI_OPT_SSL_VERIFY_SERVER_CERT if available (default verify true)
if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
}

$clientFlags = $dbSslEnable ? MYSQLI_CLIENT_SSL : 0;

$connected = @mysqli_real_connect(
    $conn,
    $dbHost,
    $dbUser,
    $dbPass,
    $dbName,
    (int)$dbPort,
    null,
    $clientFlags
);

if (!$connected) {
    die('Connection failed: ' . mysqli_connect_error());
}
?>
