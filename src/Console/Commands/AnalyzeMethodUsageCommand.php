<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Illuminate\Console\Command;
use Gleman17\LaravelTools\Services\AnalyzeMethodService;

class AnalyzeMethodUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze method usage across the application and generate a report of external calls';

    public function __construct()
    {
        $this->signature = config('gleman17_laravel_tools.command_signatures.analyze_usages',
                'tools:analyze-usages') .
            ' {--path=app : The path to analyze relative to the Laravel root}'.
            ' {--output=method_usage.csv : The output file name}'.
            ' {--min-calls=1 : Minimum number of external calls to include in report}';

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $path = base_path($this->option('path'));
        $outputFile = $this->option('output');
        $minCalls = (int) $this->option('min-calls');

        $this->info('Starting method usage analysis...');

        try {
            $analyzer = new AnalyzeMethodService(
                function ($current, $total, $phase) {
                    static $progressBar;
                    if (!isset($progressBar)) {
                        $this->info($phase . '...');
                        $progressBar = $this->output->createProgressBar($total);
                        $progressBar->start();
                    }
                    $progressBar->setProgress($current);
                    if ($current === $total) {
                        $progressBar->finish();
                        $this->newLine();
                        $progressBar = null;
                    }
                }
            );

            $report = $analyzer->analyze($path, $minCalls);
            $this->generateReport($report, $outputFile);

            $this->info("Analysis complete! Report generated at: {$outputFile}");
            return 0;

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Generate the CSV report from the analysis data.
     *
     * @param array $reportData The analyzed data to write to the report
     * @param string $outputFile The path to the output file
     * @return void
     */
    private function generateReport(array $reportData, string $outputFile)
    {
        $output = fopen($outputFile, 'w');
        fputcsv($output, [
            'Namespace',
            'Class',
            'Method',
            'Visibility',
            'External Calls',
            'File Path'
        ]);

        foreach ($reportData as $row) {
            fputcsv($output, [
                $row['namespace'],
                $row['class'],
                $row['method'],
                $row['visibility'],
                $row['external_calls'],
                $row['file_path']
            ]);
        }

        fclose($output);
    }
}
