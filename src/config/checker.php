<?php

declare(strict_types=1);

$db_driver       = $_ENV['DB_DRIVER']   ?? '';
$host            = $_ENV['DB_HOST']     ?? '';
$port            = $_ENV['DB_PORT']     ?? '';
$dbname          = $_ENV['DB_DATABASE'] ?? '';
$user            = $_ENV['DB_USERNAME'] ?? '';
$password        = $_ENV['DB_PASSWORD'] ?? '';
$connect_timeout = $_ENV['DB_TIMEOUT']  ?? 10;
$sslmode         = $_ENV['DB_SSLMODE']  ?? 'prefer';

set_exception_handler(function ($e) {
    error_log($e->getMessage(), 0);
    exit;
});

if (!in_array($db_driver, ['postgres'])) {
    error_log("Unsupported database driver: $db_driver. Supported drivers are 'postgres'.", 0);
}

if ('postgres' === $db_driver) {
    function is_pg_server_running($host, $port)
    {
        if (!is_numeric($port)) {
            error_log('Invalid port value for PostgreSQL connection: '.$port);

            return false;
        }

        $connection = @fsockopen($host, (int) $port, $errno, $errstr, 5);

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
            'pgsql:host=%s;port=%d;dbname=%s',
            htmlspecialchars($host, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($port, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($dbname, \ENT_QUOTES, 'UTF-8')
        );
        $conn_string = sprintf(
            'host=%s port=%d dbname=%s user=%s password=%s connect_timeout=%d sslmode=%s',
            htmlspecialchars($host, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($port, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($dbname, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($user, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($password, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($connect_timeout, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sslmode, \ENT_QUOTES, 'UTF-8')
        );

        $conn = @pg_connect($conn_string);

        if ($conn) {
            $result = pg_query($conn, 'SELECT version();');

            if ($result) {
                $row = pg_fetch_row($result);

                $result = pg_query($conn, 'SELECT 1 FROM users LIMIT 1');

                if (!$result) {
                    error_log(pg_last_error($conn), 0);
                }

                pg_free_result($result);
            } else {
                error_log('Error executing query: '.pg_last_error($conn), 0);
            }

            pg_close($conn);

            $pdo = new PDO($dsn, $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } else {
            error_log('Failed to connect to PostgreSQL: '.pg_last_error(), 0);
        }
    } else {
        error_log('Unable to reach the PostgreSQL server. Please ensure the server is running and accessible.', 0);
        exit;
    }
}
