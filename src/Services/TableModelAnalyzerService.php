<?php

namespace Gleman17\LaravelTools\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;

class TableModelAnalyzerService
{
    /** @var string[] */
    protected array $laravelTables = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'sessions',
    ];

    /**
     * Find tables without corresponding models.
     *
     * @return string[] Array of table names that do not have corresponding models
     */
    public function findTablesWithoutModels(): array
    {
        $tableNames = (new DatabaseTableService)->getDatabaseTables();
        $modelTableNames = $this->getModelTableNames();

        // Convert table objects to strings
        $stringTableNames = array_map(function ($table) {
            if (is_object($table)) {
                if (method_exists($table, '__toString')) {
                    return (string)$table;
                }
                $keys = array_keys((array)$table);
                if (!empty($keys)) {
                    return $table->{$keys[0]};
                }

            } elseif (is_string($table)) {
                return $table;
            }
            return ''; // Handle cases where the table is neither object nor string.
        }, $tableNames);

        $singularModelTableNames = array_map(fn($name) => Str::singular($name), $modelTableNames);

        $modelDifference = array_diff($stringTableNames, $modelTableNames);
        $plural = array_diff(
            $modelDifference,
            $this->laravelTables
        );

        return array_diff($plural, $singularModelTableNames);
    }
    /**
     * Get table names associated with models.
     *
     * @return string[] Array of table names
     */
    public function getModelTableNames(): array
    {
        $modelsPath = app_path('Models');
        $models = array_filter(array_map(function ($file) {
            $model = 'App\\Models\\' . $file->getFilenameWithoutExtension();
            return class_exists($model) ? $model : null;
        }, File::allFiles($modelsPath)));

        return array_map(function ($model) {
            $instance = new $model;
            return method_exists($instance, 'getTable') ? $instance->getTable() : Str::snake(Str::plural(class_basename($model)));
        }, $models);
    }

    /**
     * Create models for the provided tables.
     *
     * @param string[] $tables Array of table names
     * @return array<string> Array of success or failure messages for each table
     */
    public function createModelsForTables(array $tables): array
    {
        $createdModels = [];
        foreach ($tables as $table) {
            $modelName = Str::studly(Str::singular($table));

            if (File::exists(app_path("Models/{$modelName}.php"))) {
                $createdModels[$table] = "Model for table $table already exists: $modelName";
                continue;
            }

            try {
                Artisan::call('make:model', [
                    'name' => $modelName,
                ]);
                $createdModels[$table] = "Created model for table $table: $modelName";
            } catch (\Exception $e) {
                $createdModels[$table] = "Failed to create model for table $table: " . $e->getMessage();
            }
        }

        return $createdModels;
    }
}
