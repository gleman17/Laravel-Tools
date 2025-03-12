<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Illuminate\Console\Command;
use Gleman17\LaravelTools\Models\AIQueryCache;

class ClearAICacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the AI query cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = AIQueryCache::count();
        AIQueryCache::truncate();

        $this->info("AI query cache cleared. {$count} entries removed.");

        return Command::SUCCESS;
    }
}
