<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
class CustomCommandsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tools:custom-commands {--only : List only commands created in this application}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all custom commands registered in the application';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $commands = $this->getLaravel()->make('Illuminate\Contracts\Console\Kernel')->all();

        // Get only commands option
        $onlyAppCommands = $this->option('only');

        // Filter out Laravel core commands and potentially third-party package commands
        $customCommands = collect($commands)->filter(function ($command) use ($onlyAppCommands) {
            $className = get_class($command);

            // Filter out Laravel and Symfony commands
            $isNotCore = !str_starts_with($className, 'Illuminate\\') &&
                !str_starts_with($className, 'Laravel\\') &&
                !str_starts_with($className, 'Symfony\\');

            if (!$onlyAppCommands) {
                return $isNotCore;
            }

            // When --only flag is set, include only App namespace commands
            return $isNotCore && str_starts_with($className, 'App\\');
        });

        if ($customCommands->isEmpty()) {
            $this->info('No custom commands found in your application.');
            return SymfonyCommand::SUCCESS;
        }

        // Group commands by namespace like php artisan does
        $namespaces = [];

        foreach ($customCommands as $command) {
            $nameParts = explode(':', $command->getName());

            if (count($nameParts) > 1) {
                $namespace = $nameParts[0];
            } else {
                $namespace = 'app';
            }

            if (!isset($namespaces[$namespace])) {
                $namespaces[$namespace] = [];
            }

            $namespaces[$namespace][] = $command;
        }

        // Sort namespaces alphabetically
        ksort($namespaces);

        // Display commands grouped by namespace
        foreach ($namespaces as $namespace => $commands) {
            $this->line('');
            $this->line("<comment>{$namespace}</comment>");

            // Sort commands alphabetically within each namespace
            usort($commands, function ($a, $b) {
                return strcmp($a->getName(), $b->getName());
            });

            $width = $this->getColumnWidth($commands);

            // Display commands in a formatted way
            foreach ($commands as $command) {
                $name = $command->getName();
                $description = $command->getDescription();

                $spacer = str_repeat(' ', $width - strlen($name));

                $this->line("  <info>{$name}</info>{$spacer}{$description}");
            }
        }

        return SymfonyCommand::SUCCESS;
    }

    /**
     * Get the maximum width of command names for proper spacing
     *
     * @param array $commands
     * @return int
     */
    private function getColumnWidth(array $commands): int
    {
        $width = 0;

        foreach ($commands as $command) {
            $width = max($width, strlen($command->getName()) + 4);
        }

        return $width;
    }
}
