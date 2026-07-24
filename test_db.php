<?php
// Simple database connection diagnostic script
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Diagnostic</title>
    <style>
        body { font-family: monospace, sans-serif; padding: 20px; background: #121212; color: #e0e0e0; }
        .card { background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333; max-width: 800px; margin: 0 auto; }
        .success { color: #4caf50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td, th { padding: 8px 12px; border-bottom: 1px solid #333; text-align: left; }
    </style>
</head>
<body>
<div class="card">
    <h2>Database Connection Diagnostic</h2>
    <?php
    $envPath = __DIR__ . '/.env';
    echo "<p>Checking <code>.env</code> file: " . (file_exists($envPath) ? "<span class='success'>FOUND</span> ($envPath)" : "<span class='error'>NOT FOUND</span>") . "</p>";

    $isLocal = defined('IS_LOCALHOST') ? IS_LOCALHOST : true;
    $host = app_env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
    $user = app_env('DB_USER', defined('DB_USER') ? DB_USER : ($isLocal ? 'root' : 'cons_pc_db'));
    $pass = app_env('DB_PASS', defined('DB_PASS') ? DB_PASS : ($isLocal ? '' : 'e3sEe1sf!g6+uoak'));
    $name = app_env('DB_NAME', defined('DB_NAME') ? DB_NAME : ($isLocal ? 'pc_db' : 'cons_pc_db'));
    $port = (int)(app_env('DB_PORT', getenv('DB_PORT')) ?: 3306);
    if ($port <= 0) $port = 3306;

    echo "<table>";
    echo "<tr><th>Setting</th><th>Resolved Value</th></tr>";
    echo "<tr><td>DB_HOST</td><td>" . htmlspecialchars($host) . "</td></tr>";
    echo "<tr><td>DB_PORT</td><td>" . htmlspecialchars((string)$port) . "</td></tr>";
    echo "<tr><td>DB_USER</td><td>" . htmlspecialchars($user) . "</td></tr>";
    echo "<tr><td>DB_NAME</td><td>" . htmlspecialchars($name) . "</td></tr>";
    echo "<tr><td>DB_PASS</td><td>" . (empty($pass) ? '<em>(empty)</em>' : '•••••••• (' . strlen($pass) . ' chars)') . "</td></tr>";
    echo "</table>";

    echo "<h3>Connection Test Result</h3>";
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($host, $user, $pass, $name, $port);

    if ($conn->connect_error) {
        echo "<p class='error'>❌ CONNECTION FAILED!</p>";
        echo "<p><strong>Error Message:</strong> " . htmlspecialchars($conn->connect_error) . "</p>";
        echo "<p><strong>Error Code:</strong> " . $conn->connect_errno . "</p>";
        
        // Helpful hints based on error code
        echo "<h4>Possible Causes:</h4><ul>";
        if ($conn->connect_errno == 1045) {
            echo "<li><strong>Access Denied (1045):</strong> The database user <code>$user</code> or password in <code>.env</code> is incorrect, or the user does not have permission to access database <code>$name</code> on host <code>$host</code>.</li>";
            echo "<li><em>cPanel Note:</em> If hosted on cPanel, database usernames and names are usually prefixed with your cPanel username (e.g., <code>cpaneluser_$name</code> and <code>cpaneluser_$user</code>).</li>";
        } elseif ($conn->connect_errno == 1049) {
            echo "<li><strong>Unknown Database (1049):</strong> The database <code>$name</code> does not exist on MySQL host <code>$host</code>. Import <code>DATABASE/DEPLOY.sql</code> into phpMyAdmin or create database <code>$name</code> first.</li>";
        } elseif ($conn->connect_errno == 2002) {
            echo "<li><strong>Cannot Connect (2002):</strong> MySQL server is not running at host <code>$host</code> on port <code>$port</code>, or host should be <code>127.0.0.1</code> instead of <code>localhost</code>.</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='success'>✅ CONNECTED SUCCESSFULLY!</p>";
        echo "<p>MySQL Server Version: " . $conn->server_info . "</p>";

        // Table check
        $res = $conn->query("SHOW TABLES");
        if ($res) {
            $tables = [];
            while ($row = $res->fetch_array()) {
                $tables[] = $row[0];
            }
            echo "<p>Found <strong>" . count($tables) . "</strong> table(s): " . (empty($tables) ? '<em>(none)</em>' : htmlspecialchars(implode(', ', $tables))) . "</p>";
        }
    }
    ?>
</div>
</body>
</html>
