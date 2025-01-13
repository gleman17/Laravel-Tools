<?php

namespace Gleman17\LaravelTools\Services;
use Illuminate\Support\Facades\DB;

class DatabaseTableService
{
    /**
     * @return array<int, string>
     */
    public function getDatabaseTables(): array
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mysql':
                return array_map(
                    fn($table) => $table->Tables_in_database,  // Use the actual column name
                    DB::select('SHOW TABLES')
                );

            case 'sqlite':
                return array_map(
                    fn($table) => $table->name,
                    DB::select("SELECT name FROM sqlite_master WHERE type='table'")
                );

            case 'pgsql': // PostgreSQL
                return array_map(
                    fn($table) => $table->tablename,
                    DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
                );

            case 'sqlsrv': // SQL Server
                return array_map(
                    fn($table) => $table->TABLE_NAME,
                    DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'")
                );

            default:
                throw new \RuntimeException("Unsupported database driver: $driver");
        }
    }
}
