<?php
// Custom error handler - capture fatal/parse errors and render a friendly error card
// This file is intended to be auto-prepended via .htaccess (auto_prepend_file) or included early.

// Prevent double registration
if (!defined('CUSTOM_ERROR_HANDLER_LOADED')) {
    define('CUSTOM_ERROR_HANDLER_LOADED', true);

    // Capture non-fatal warnings/notices as before
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Only log warnings and notices here; fatal errors are handled in shutdown handler
        if (in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE])) {
            error_log("Captured PHP notice/warning: $errstr in $errfile:$errline");
            return true; // prevent default handler
        }
        return false; // let PHP handle other errors
    });

    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            // Clean output buffers to avoid partial HTML
            while (ob_get_level()) {
                ob_end_clean();
            }

            http_response_code(500);

            // Simple HTML error card matching app styles (no dependencies)
            echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Application Error</title>";
            // Use CDN bootstrap so the error page still looks decent even when static paths break
            echo "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css\">";
            echo "</head><body style=\"padding:20px;font-family:Arial,Helvetica,sans-serif\">";
            echo "<div style=\"max-width:900px;margin:40px auto;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;padding:20px;border-radius:6px\">";
            // Try to embed logo so it displays even on fatal errors
            $logoData = '';
            $p = __DIR__ . '/../images/aamusted-logo.png';
            $f = __DIR__ . '/../images/aamustedLog.png';
            if (file_exists($p)) {
                $b = @file_get_contents($p);
                if ($b !== false) $logoData = 'data:image/png;base64,' . base64_encode($b);
            } elseif (file_exists($f)) {
                $b = @file_get_contents($f);
                if ($b !== false) $logoData = 'data:image/png;base64,' . base64_encode($b);
            }
            if ($logoData !== '') {
                $img = htmlspecialchars($logoData, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo "<div style=\"text-align:center;margin-bottom:12px;\"><img src=\"" . $img . "\" alt=\"Logo\" style=\"max-height:96px;\"/></div>";
            }
            echo "<h2 style=\"margin-top:0;font-size:18px\">Unexpected system error</h2>";
            echo "<p>An unexpected error occurred and the request could not be completed.</p>";
            echo "<hr />";
            echo "<pre style=\"white-space:pre-wrap;word-break:break-word;background:#fff;padding:10px;border-radius:4px;overflow:auto;\">";
            echo htmlspecialchars($error['message'] . " in " . $error['file'] . ":" . $error['line']);
            echo "</pre>";
            echo "</div></body></html>";

            // Ensure nothing else is executed
            exit(1);
        }
    });
}
