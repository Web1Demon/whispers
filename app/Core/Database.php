<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $driver = $this->config['driver'] ?? 'mysql';

            try {
                if ($driver === 'mysql') {
                    $host = $this->config['host'] ?? '127.0.0.1';
                    $port = $this->config['port'] ?? 3306;
                    $dbname = $this->config['database'] ?? 'whisper_app';
                    $user = $this->config['username'] ?? 'root';
                    $pass = $this->config['password'] ?? '';
                    $charset = $this->config['charset'] ?? 'utf8mb4';

                    // 1. Ensure database exists
                    $initDsn = "mysql:host={$host};port={$port}";
                    $initPdo = new PDO($initDsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $initPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci;");
                    unset($initPdo);

                    // 2. Connect to the specific database
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ];

                    self::$pdo = new PDO($dsn, $user, $pass, $options);
                    self::$pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
                } elseif ($driver === 'sqlite') {
                    $dbPath = $this->config['database'] ?? __DIR__ . '/../../database/whisper.sqlite';
                    $dir = dirname($dbPath);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }

                    $dsn = "sqlite:{$dbPath}";
                    self::$pdo = new PDO($dsn, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);

                    self::$pdo->exec("PRAGMA journal_mode = WAL;");
                    self::$pdo->exec("PRAGMA synchronous = NORMAL;");
                    self::$pdo->exec("PRAGMA foreign_keys = ON;");
                    self::$pdo->exec("PRAGMA busy_timeout = 5000;");
                } else {
                    throw new RuntimeException("Unsupported database driver: {$driver}");
                }
            } catch (PDOException $e) {
                throw new RuntimeException("Database connection error: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$pdo;
    }

    public function getDriver(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->getConnection();
        if ($pdo->inTransaction()) {
            return $callback($this);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
