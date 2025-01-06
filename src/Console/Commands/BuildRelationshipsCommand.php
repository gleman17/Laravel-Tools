<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Gleman17\LaravelTools\Services\BuildRelationshipsService;
use Illuminate\Console\Command;

class BuildRelationshipsCommand extends Command
{
    protected $signature;
    protected $description = 'Builds Eloquent relationships between models.';
    private BuildRelationshipsService $service;

    protected $help = <<<EOT
This command builds Eloquent relationships between specified models or across all models in the project.

Relations are found by building a graph of the relationships in the database using the convention that a foreign
key is assumed to be named {table}_id where table is the singularized version of the table name.

If the relationship is only one table away, it will use HasMany or BelongsTo. So if the "posts"
table has a foreign key named "user_id", the "users" users table (and User model) will have a HasMany relationship to
the Post model.

If the relationship requires an intermediate table, it will use HasManyThrough or
staudenmeir/belongs-to-through.

If the relationship involves multiple tables, it will use staudenmeir/eloquent-has-many-deep for each
direction.

This will make changes to the source code in your Models directory, so make sure
to backup your code before running this command.

### Usage:
- Build relationships for all models:
  php artisan tools:build-relationships --all

- Build relationships for a specific starting model and all subsequent models:
  php artisan tools:build-relationships {startModel} {endModel}

- Build relationships for a specific starting model and a specific ending model:
  php artisan tools:build-relationships {startModel} {endModel}

### Options:
- {start} (optional): The starting model to build relationships for.
- {end} (optional): The ending model to build relationships for.
- --all: Processes all models in the project. Use with caution as it may affect many models.
EOT;

    public function __construct(?BuildRelationshipsService $service = null)
    {
        $this->service = $service ?? new BuildRelationshipsService();
        $this->signature = config('gleman17_laravel_tools.command_signatures.remove_relationships',
            'tools:build-relationships') .
            ' {start?} {end?} {--all}';
        parent::__construct();

    }

    public function handle(): int
    {
        $startModel = $this->argument('start');
        $endModel = $this->argument('end');
        $all = $this->option('all');

        if (!$startModel && $all) {
            if (!$this->confirm('Are you sure? This will process ALL models.')) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $messages = $this->service->build($startModel, $endModel, $all);

        foreach ($messages as $message) {
            $this->info($message);
        }

        return Command::SUCCESS;
    }
}
