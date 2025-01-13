<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Illuminate\Console\Command;
use PhpParser\Error;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class AnalyzeMethodUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:methods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze method usage across the application and generate a report of external calls';

    private $parser;
    private $nodeFinder;
    private $classMap = [];
    private $progressBar;

    public function __construct()
    {
        $this->signature = config('gleman17_laravel_tools.command_signatures.analyze_usages',
                'tools:analyze-usages') .
              '{--path=app : The path to analyze relative to the Laravel root} '.
              '{--output=method_usage.csv : The output file name} '.
              '{--min-calls=1 : Minimum number of external calls to include in report}';

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->initializeTools();


        $path = base_path($this->option('path'));
        $outputFile = $this->option('output');
        $minCalls = (int) $this->option('min-calls');

        if (!is_dir($path)) {
            $this->error("Directory not found: {$path}");
            return 1;
        }

        $this->info('Starting method usage analysis...');

        // First pass: Build class map
        $this->info('Building class map...');
        $this->buildClassMap($path);

        // Second pass: Count method calls
        $this->info('Analyzing method calls...');
        $this->countMethodCalls($path);

        // Generate report
        $this->generateReport($outputFile, $minCalls);

        $this->info("Analysis complete! Report generated at: {$outputFile}");
        return 0;
    }

    private function initializeTools()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->nodeFinder = new NodeFinder;
    }

    private function buildClassMap(string $directory)
    {
        $files = $this->getPhpFiles($directory);

        $this->progressBar = $this->output->createProgressBar(count($files));
        $this->progressBar->start();

        foreach ($files as $file) {
            try {
                $code = file_get_contents($file);
                $ast = $this->parser->parse($code);

                // First find the namespace
                $namespace = '';
                foreach ($ast as $node) {
                    if ($node instanceof Node\Stmt\Namespace_ && $node->name) {
                        $namespace = $node->name->toString();
                        break;
                    }
                }

                // Find all classes in the file
                $classes = $this->nodeFinder->findInstanceOf($ast, Class_::class);

                foreach ($classes as $class) {
                    $className = $class->name->toString();
                    $this->classMap[$className] = [
                        'methods' => [],
                        'file' => $file,
                        'namespace' => $namespace
                    ];

                    // Store all methods of the class
                    foreach ($class->getMethods() as $method) {
                        if (!$method->isPrivate()) {  // Only analyze public and protected methods
                            $methodName = $method->name->toString();
                            $this->classMap[$className]['methods'][$methodName] = [
                                'count' => 0,
                                'visibility' => $this->getMethodVisibility($method),
                                'calledFrom' => []
                            ];
                        }
                    }
                }
            } catch (Error $e) {
                $this->warn("Parse error in $file: " . $e->getMessage());
            }

            $this->progressBar->advance();
        }

        $this->progressBar->finish();
        $this->newLine();
    }

    private function countMethodCalls(string $directory)
    {
        $files = $this->getPhpFiles($directory);

        $this->progressBar = $this->output->createProgressBar(count($files));
        $this->progressBar->start();

        // Reset all counts
        foreach ($this->classMap as &$classInfo) {
            foreach ($classInfo['methods'] as &$methodInfo) {
                $methodInfo['count'] = 0;
                $methodInfo['calledFrom'] = [];  // Track unique call locations
            }
        }

        foreach ($files as $file) {
            try {
                $code = file_get_contents($file);
                $ast = $this->parser->parse($code);

                // Get the current class being analyzed
                $currentClass = null;
                $currentNamespace = null;
                $classes = $this->nodeFinder->findInstanceOf($ast, Class_::class);
                if (!empty($classes)) {
                    $currentClass = $classes[0]->name->toString();
                    $currentNamespace = $this->getNamespace($classes[0]);
                    if ($currentNamespace) {
                        $currentClass = $currentNamespace . '\\' . $currentClass;
                    }
                }

                // Find all method calls
                $methodCalls = $this->nodeFinder->find($ast, function(Node $node) {
                    return ($node instanceof MethodCall || $node instanceof StaticCall) && !($node->getAttribute('parent') instanceof Node\Expr\MethodCall);
                });

                foreach ($methodCalls as $call) {
                    $this->processCall($call, $currentClass, $currentNamespace, $file);
                }
            } catch (Error $e) {
                $this->warn("Parse error in $file: " . $e->getMessage());
            }

            $this->progressBar->advance();
        }

        // Update final counts based on unique call locations
        foreach ($this->classMap as &$classInfo) {
            foreach ($classInfo['methods'] as &$methodInfo) {
                $methodInfo['count'] = count($methodInfo['calledFrom']);
                unset($methodInfo['calledFrom']);  // Remove from final output
            }
        }

        $this->progressBar->finish();
        $this->newLine();
    }

    private function processCall($call, $currentClass, $currentNamespace, $file)
    {
        if ($call instanceof MethodCall && is_string($call->name->name)) {
            $methodName = $call->name->name;
            $this->processMethodCall($currentClass, $methodName, $file);
        } elseif ($call instanceof StaticCall && is_string($call->name->name)) {
            $methodName = $call->name->name;
            if ($call->class instanceof Node\Name) {
                $calledClass = $call->class->toString();
                $fullyQualifiedClass = $this->resolveClassName($calledClass, $currentNamespace);

                if ($fullyQualifiedClass !== $currentClass &&
                    isset($this->classMap[$calledClass]) &&
                    isset($this->classMap[$calledClass]['methods'][$methodName])) {
                    // Add unique call location
                    $location = $file . ':' . $call->getStartLine();
                    $this->classMap[$calledClass]['methods'][$methodName]['calledFrom'][$location] = true;
                }
            }
        }
    }

    private function processMethodCall($currentClass, $methodName, $file)
    {
        // Skip common Laravel dynamic methods
        if ($this->isLaravelDynamicMethod($methodName)) {
            return;
        }

        foreach ($this->classMap as $className => $classInfo) {
            if ($className !== $currentClass &&
                isset($classInfo['methods'][$methodName])) {
                // Add unique call location
                $location = $file . ':' . $methodName;
                $this->classMap[$className]['methods'][$methodName]['calledFrom'][$location] = true;
            }
        }
    }

    private function isLaravelDynamicMethod(string $methodName): bool
    {
        // Common Laravel query builder method prefixes
        $dynamicPrefixes = [
            'where',
            'orWhere',
            'andWhere',
            'has',
            'orHas',
            'whereHas',
            'orWhereHas',
            'doesntHave',
            'orDoesntHave',
            'whereDoesntHave',
            'orWhereDoesntHave',
            'with',
            'without',
            'find',
            'firstWhere',
            'updateOrCreate',
            'firstOrCreate',
            'orderBy',
            'groupBy',
            'having',
            'skip',
            'take'
        ];

        // Check if the method starts with any of these prefixes
        foreach ($dynamicPrefixes as $prefix) {
            if (strpos($methodName, $prefix) === 0) {
                return true;
            }
        }

        // Framework interface methods and common methods
        $frameworkMethods = [
            // Eloquent methods
            'toArray',
            'toJson',
            'fresh',
            'refresh',
            'save',
            'update',
            'delete',
            'restore',
            'trashed',
            'replicate',
            'is',
            'isNot',
            'exists',
            'wasRecentlyCreated',
            'wasChanged',
            'isDirty',
            'isClean',
            'getOriginal',
            'getAttribute',
            'setAttribute',
            'fill',
            'newQuery',
            'newModelQuery',
            'newCollection',

            // Collection methods
            'map',
            'filter',
            'reduce',
            'each',
            'every',
            'some',
            'contains',
            'first',
            'last',
            'get',
            'all',
            'merge',
            'diff',
            'intersect',
            'unique',
            'only',
            'except',
            'groupBy',
            'sortBy',
            'sortByDesc',
            'pluck',
            'values',
            'keys',
            'count',
            'isEmpty',
            'isNotEmpty',

            // Query Builder methods
            'select',
            'addSelect',
            'distinct',
            'from',
            'join',
            'leftJoin',
            'rightJoin',
            'crossJoin',
            'union',
            'unionAll',
            'raw',
            'selectRaw',
            'whereRaw',
            'havingRaw',
            'orderByRaw',
            'offset',
            'limit',
            'forPage',
            'chunk',
            'chunkById',

            // Serialization methods
            'jsonSerialize',
            'serialize',
            'unserialize',

            // ArrayAccess methods
            'offsetExists',
            'offsetGet',
            'offsetSet',
            'offsetUnset',

            // Generic interface methods
            'toString',
            'clone',
            '__toString',
            '__get',
            '__set',
            '__isset',
            '__unset',
            '__call',
            '__callStatic'
        ];

        return in_array($methodName, $frameworkMethods);
    }

    private function generateReport(string $outputFile, int $minCalls)
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

        foreach ($this->classMap as $className => $classInfo) {
            foreach ($classInfo['methods'] as $methodName => $methodInfo) {
                if ($methodInfo['count'] >= $minCalls) {
                    fputcsv($output, [
                        $classInfo['namespace'] ?? '',
                        $className,
                        $methodName,
                        $methodInfo['visibility'],
                        $methodInfo['count'],
                        $classInfo['file']
                    ]);
                }
            }
        }

        fclose($output);
    }

    private function getPhpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function getNamespace($class): ?string
    {
        // First try to get from direct parent node
        $namespace = '';
        $node = $class;
        while ($node = $node->getAttribute('parent')) {
            if ($node instanceof Node\Stmt\Namespace_) {
                return $node->name ? $node->name->toString() : null;
            }
        }
        return null;
    }

    private function getMethodVisibility(ClassMethod $method): string
    {
        if ($method->isPublic()) return 'public';
        if ($method->isProtected()) return 'protected';
        return 'private';
    }

    private function resolveClassName(string $class, ?string $currentNamespace): string
    {
        // If it's already fully qualified
        if (strpos($class, '\\') === 0) {
            return substr($class, 1);
        }

        // If it's in the current namespace
        if ($currentNamespace) {
            return $currentNamespace . '\\' . $class;
        }

        return $class;
    }
}
