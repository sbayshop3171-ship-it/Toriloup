<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;

trait ImportsLocationSql
{
    protected function importLocationSql(string $sqlPath, string $table): void
    {
        if (!file_exists($sqlPath)) {
            return;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        preg_match_all('/INSERT\s+INTO\s+`?' . preg_quote($table, '/') . '`?.*?;/is', $sql, $matches);
        if (empty($matches[0])) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        foreach ($matches[0] as $statement) {
            if ($driver === 'sqlite') {
                $statement = preg_replace('/^INSERT\s+INTO/i', 'INSERT OR IGNORE INTO', $statement);
                // phpMyAdmin MySQL dumps escape apostrophes with backslashes; SQLite expects doubled apostrophes.
                $statement = str_replace("\\'", "''", $statement);
            } elseif ($driver === 'mysql') {
                $statement = preg_replace('/^INSERT\s+INTO/i', 'INSERT IGNORE INTO', $statement);
            }

            DB::unprepared($statement);
        }
    }
}
