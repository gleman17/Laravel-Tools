<?php

namespace Gleman17\LaravelTools\Services;
use Illuminate\Support\Facades\DB;

class DatabaseTableService
{

    private array $tableMetadata;

    public function getMetadata(): array
    {
        $tables = $this->getDatabaseTables();
        $metadata = [];
        foreach ($tables as $table) {
            $metadata[$table] = $this->getTableColumns($table);
        }
        return $metadata;
    }

    /**
     * @return array<int, string>
     */
    public function getDatabaseTables(): array
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mysql':
                $tables = DB::select('SHOW TABLES');
                return array_map(
                    function($table) {
                        // Convert object to array and get first value
                        return current((array) $table);
                    },
                    $tables
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

    public function tableToModelName(string $table): string
    {
        return Str::studly(Str::singular($table));
    }

    public function foreignKeyToRelationName(string $foreignKey): string
    {
        return Str::plural(Str::before($foreignKey, '_id'));
    }

    public function getTableColumns(string $table): array
    {
        if (isset($this->tableMetadata[$table])) {
            return $this->tableMetadata[$table];
        }

        $driver = DB::getDriverName();
        $columns = [];

        switch ($driver) {
            case 'mysql':
                $columnsInfo = DB::select("SHOW COLUMNS FROM $table");
                foreach ($columnsInfo as $column) {
                    $columns[$column->Field] = [
                        'type' => $this->parseColumnType($column->Type),
                        'nullable' => $column->Null === 'YES',
                        'default' => $column->Default,
                        'key' => $column->Key,
                    ];
                }
                break;

            case 'pgsql':
                $columnsInfo = DB::select("
                    SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_name = ?
                    AND table_schema = 'public'
                ", [$table]);

                foreach ($columnsInfo as $column) {
                    $columns[$column->column_name] = [
                        'type' => $column->data_type,
                        'nullable' => $column->is_nullable === 'YES',
                        'default' => $column->column_default,
                    ];
                }
                break;

            case 'sqlite':
                $columnsInfo = DB::select("PRAGMA table_info($table)");
                foreach ($columnsInfo as $column) {
                    $columns[$column->name] = [
                        'type' => $column->type,
                        'nullable' => !$column->notnull,
                        'default' => $column->dflt_value,
                    ];
                }
                break;

            case 'sqlsrv':
                $columnsInfo = DB::select("
                    SELECT
                        c.name AS column_name,
                        t.name AS data_type,
                        c.is_nullable,
                        c.default_object_id
                    FROM sys.columns c
                    INNER JOIN sys.types t ON c.system_type_id = t.system_type_id
                    WHERE OBJECT_ID = OBJECT_ID(?)
                ", [$table]);

                foreach ($columnsInfo as $column) {
                    $columns[$column->column_name] = [
                        'type' => $column->data_type,
                        'nullable' => $column->is_nullable,
                        'default' => $column->default_object_id,
                    ];
                }
                break;

            default:
                throw new \RuntimeException("Unsupported database driver: $driver");
        }

        $this->tableMetadata[$table] = $columns;
        return $columns;
    }

    private function parseColumnType(string $type): string
    {
        // Extract base type from MySQL type definition
        // e.g., "varchar(255)" becomes "varchar"
        preg_match('/^([a-z]+)/', $type, $matches);
        return $matches[1] ?? $type;
    }

    public function getTableMetadata(): array
    {
        return $this->tableMetadata;
    }
}
