<?php

$db_driver = $_ENV['DB_DRIVER'] ?? ''; // postgres
$host = $_ENV['DB_HOST'] ?? '';
$port = $_ENV['DB_PORT'] ?? '';
$dbname = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$password = $_ENV['DB_PASSWORD'] ?? '';
$connect_timeout = $_ENV['DB_TIMEOUT'] ?? 10;
$sslmode = $_ENV['DB_SSLMODE'] ?? 'prefer';

//header('Content-Type: application/vnd.api+json; charset=UTF-8');

function secure_output($message, $status = 'error') {
	header('Content-Type: application/vnd.api+json; charset=UTF-8');
    echo json_encode(['status' => $status, 'message' => $message]) . "\n";
}

set_exception_handler(function($e) {
    error_log($e->getMessage(), 0);
    secure_output("Fatal error: " . $e->getMessage());
    exit;
});

if (!in_array($db_driver, ['postgres'])) {
    throw new Exception("Unsupported database driver: $db_driver. Supported drivers are 'postgres'.");
}

if ($db_driver === 'postgres') {
    function is_pg_server_running($host, $port) {
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);

        if ($connection) {
            fclose($connection);
            return true;
        } else {
            error_log("PostgreSQL server is not reachable: $errstr ($errno)", 0);
            return false;
        }
    }

    if (is_pg_server_running($host, $port)) {
        $dsn = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s",
            htmlspecialchars($host, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($port, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($dbname, ENT_QUOTES, 'UTF-8')
        );
        $conn_string = sprintf(
            "host=%s port=%d dbname=%s user=%s password=%s connect_timeout=%d sslmode=%s",
            htmlspecialchars($host, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($port, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($dbname, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($user, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($password, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($connect_timeout, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sslmode, ENT_QUOTES, 'UTF-8')
        );

        $conn = @pg_connect($conn_string);

        if ($conn) {
            //secure_output("Connection to PostgreSQL established successfully.", 'success');

            $result = pg_query($conn, "SELECT version();");

            if ($result) {
                $row = pg_fetch_row($result);
                //secure_output("PostgreSQL version: " . htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8'), 'success');

                $result = pg_query($conn, "SELECT 1 FROM users LIMIT 1");
                if (!$result) {
                    throw new Exception(pg_last_error($conn));
                }

                pg_free_result($result);
            } else {
                throw new Exception("Error executing query: " . pg_last_error($conn));
            }

            pg_close($conn);

            if (isset($dsn)) {
                $pdo = new PDO($dsn, $user, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }

        } else {
            throw new Exception("Failed to connect to PostgreSQL: " . pg_last_error());
        }
    } else {
        secure_output("Unable to reach the PostgreSQL server. Please ensure the server is running and accessible.", 'error');
        exit;
    }
}
