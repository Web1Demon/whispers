<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Core\Database;
use RuntimeException;

class Migrator
{
    private Database $db;
    private string $schemaDir;

    public function __construct(Database $db, ?string $schemaDir = null)
    {
        $this->db = $db;
        $this->schemaDir = $schemaDir ?? __DIR__ . '/../../../database';
    }

    public function run(): void
    {
        $driver = $this->db->getDriver();
        $schemaFile = $driver === 'mysql' 
            ? $this->schemaDir . '/mysql_schema.sql' 
            : $this->schemaDir . '/schema.sql';

        if (!file_exists($schemaFile)) {
            throw new RuntimeException("Schema file not found at: {$schemaFile}");
        }

        $sql = file_get_contents($schemaFile);
        $pdo = $this->db->getConnection();

        // Split multi-statement SQL by semicolon for safe execution if necessary
        if ($driver === 'mysql') {
            $pdo->exec($sql);
        } else {
            $pdo->exec($sql);
        }
    }

    public function reset(): void
    {
        $pdo = $this->db->getConnection();
        $driver = $this->db->getDriver();

        if ($driver === 'mysql') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("DROP TABLE IF EXISTS likes;");
            $pdo->exec("DROP TABLE IF EXISTS comments;");
            $pdo->exec("DROP TABLE IF EXISTS posts;");
            $pdo->exec("DROP TABLE IF EXISTS rate_limits;");
            $pdo->exec("DROP TABLE IF EXISTS identities;");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        } else {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
            $pdo->exec("DROP TABLE IF EXISTS likes;");
            $pdo->exec("DROP TABLE IF EXISTS comments;");
            $pdo->exec("DROP TABLE IF EXISTS posts;");
            $pdo->exec("DROP TABLE IF EXISTS rate_limits;");
            $pdo->exec("DROP TABLE IF EXISTS identities;");
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }

        $this->run();
    }
}
