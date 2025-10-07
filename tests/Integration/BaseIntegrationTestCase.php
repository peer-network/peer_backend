<?php
declare(strict_types=1);

namespace Tests\Integration;

use Fawaz\Utils\PeerNullLogger;
use Fawaz\Utils\PeerLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class BaseIntegrationTestCase extends TestCase
{
    protected ?PDO $pdo = null;
    protected PeerLogger $logger;

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('TEST_DB_DSN');
        $user = getenv('TEST_DB_USER');
        $pass = getenv('TEST_DB_PASSWORD');
        if (!$dsn) {
            $this->markTestSkipped('TEST_DB_DSN not set; skipping DB integration tests.');
        }
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Apply SQL migrations once per test run
        if (!self::$migrated) {
            $this->runSqlMigrations($this->pdo);
            self::$migrated = true;
        }

        $this->pdo->beginTransaction();

        // Opt-in test logging: set TEST_LOGS=1 to see logs on stderr
        if (getenv('TEST_LOGS') === '1') {
            $logger = new PeerLogger('tests');
            $levelName = strtoupper((string) getenv('TEST_LOG_LEVEL')) ?: 'DEBUG';
            try {
                $level = Level::fromName($levelName);
            } catch (\Throwable) {
                $level = Level::Debug;
            }
            $handler = new StreamHandler('php://stderr', $level);
            $logger->pushHandler($handler);
            $this->logger = $logger;
        } else {
            $this->logger = new PeerLogger(null);
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    private function runSqlMigrations(PDO $pdo): void
    {
        $dir = getenv('TEST_SQL_DIR') ?: __DIR__ . '/../../sql_files_for_import';
        if (!is_dir($dir)) {
            // Fallback to project root path if running from different CWD
            $alt = getcwd() . '/sql_files_for_import';
            $dir = is_dir($alt) ? $alt : null;
        }
        if (!$dir || !is_dir($dir)) {
            return; // no SQL dir found; assume schema already present
        }

        // If core tables already exist (e.g., initialized by docker), skip applying SQL files to avoid duplicates
        try {
            $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'transactions' LIMIT 1");
            if ($stmt !== false && $stmt->fetchColumn()) {
                return;
            }
        } catch (\Throwable $e) {
            // If introspection fails, proceed cautiously with applying files
        }

        $files = glob(rtrim($dir, '/'). '/*.sql');
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) { continue; }
            // Execute as-is; Postgres PDO can handle multi-statement strings
            $pdo->exec($sql);
        }
    }
}
