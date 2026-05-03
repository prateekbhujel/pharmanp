<?php

namespace App\Core\Database;

use Illuminate\Database\DatabaseManager;

final readonly class SqlDialect
{
    public function __construct(private DatabaseManager $db) {}

    public function daysFromToday(string $dateExpression): string
    {
        if ($this->driver() === 'sqlite') {
            return "CAST(julianday({$dateExpression}) - julianday('now') AS INTEGER)";
        }

        return "DATEDIFF({$dateExpression}, CURDATE())";
    }

    public function daysUntilToday(string $dateExpression): string
    {
        if ($this->driver() === 'sqlite') {
            return "CAST(julianday('now') - julianday({$dateExpression}) AS INTEGER)";
        }

        return "DATEDIFF(CURDATE(), {$dateExpression})";
    }

    public function coalescedDaysUntilToday(string $dateColumn, string $fallbackColumn): string
    {
        return $this->daysUntilToday("COALESCE({$dateColumn}, {$fallbackColumn})");
    }

    private function driver(): string
    {
        return $this->db->connection()->getDriverName();
    }
}
