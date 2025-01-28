<?php

namespace Gleman17\LaravelTools\Services;

use PhpParser\Error;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class AnalyzeMethodService
{
    private $parser;
    private $nodeFinder;
    private $classMap = [];
    private $progressCallback;

    public function __construct(?callable $progressCallback = null)
    {
        $this->initializeTools();
        $this->progressCallback = $progressCallback;
    }

    private function initializeTools()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->nodeFinder = new NodeFinder;
    }

    public function analyze(string $directory, int $minCalls): array
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("Directory not found: {$directory}");
        }

        $this->buildClassMap($directory);
        $this->countMethodCalls($directory);

        return $this->prepareReport($minCalls);
    }

    private function buildClassMap(string $directory)
    {
        $files = $this->getPhpFiles($directory);
        $totalFiles = count($files);
        $processedFiles = 0;

        foreach ($files as $file) {
            try {
                $code = file_get_contents($file);
                $ast = $this->parser->parse($code);

                $namespace = '';
                foreach ($ast as $node) {
                    if ($node instanceof Node\Stmt\Namespace_ && $node->name) {
                        $namespace = $node->name->toString();
                        break;
                    }
                }

                $classes = $this->nodeFinder->findInstanceOf($ast, Class_::class);

                foreach ($classes as $class) {
                    $className = $class->name->toString();
                    $this->classMap[$className] = [
                        'methods' => [],
                        'file' => $file,
                        'namespace' => $namespace
                    ];

                    foreach ($class->getMethods() as $method) {
                        if (!$method->isPrivate()) {
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
                throw new \RuntimeException("Parse error in $file: " . $e->getMessage());
            }

            $processedFiles++;
            if ($this->progressCallback) {
                ($this->progressCallback)($processedFiles, $totalFiles, 'Building class map');
            }
        }
    }

    private function countMethodCalls(string $directory)
    {
        $files = $this->getPhpFiles($directory);
        $totalFiles = count($files);
        $processedFiles = 0;

        foreach ($this->classMap as &$classInfo) {
            foreach ($classInfo['methods'] as &$methodInfo) {
                $methodInfo['count'] = 0;
                $methodInfo['calledFrom'] = [];
            }
        }

        foreach ($files as $file) {
            try {
                $code = file_get_contents($file);
                $ast = $this->parser->parse($code);

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

                $methodCalls = $this->nodeFinder->find($ast, function(Node $node) {
                    return ($node instanceof MethodCall || $node instanceof StaticCall) &&
                        !($node->getAttribute('parent') instanceof Node\Expr\MethodCall);
                });

                foreach ($methodCalls as $call) {
                    $this->processCall($call, $currentClass, $currentNamespace, $file);
                }
            } catch (Error $e) {
                throw new \RuntimeException("Parse error in $file: " . $e->getMessage());
            }

            $processedFiles++;
            if ($this->progressCallback) {
                ($this->progressCallback)($processedFiles, $totalFiles, 'Analyzing method calls');
            }
        }

        foreach ($this->classMap as &$classInfo) {
            foreach ($classInfo['methods'] as &$methodInfo) {
                $methodInfo['count'] = count($methodInfo['calledFrom']);
                unset($methodInfo['calledFrom']);
            }
        }
    }

    private function prepareReport(int $minCalls): array
    {
        $report = [];
        foreach ($this->classMap as $className => $classInfo) {
            foreach ($classInfo['methods'] as $methodName => $methodInfo) {
                if ($methodInfo['count'] >= $minCalls) {
                    $report[] = [
                        'namespace' => $classInfo['namespace'] ?? '',
                        'class' => $className,
                        'method' => $methodName,
                        'visibility' => $methodInfo['visibility'],
                        'external_calls' => $methodInfo['count'],
                        'file_path' => $classInfo['file']
                    ];
                }
            }
        }
        return $report;
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
                    $location = $file . ':' . $call->getStartLine();
                    $this->classMap[$calledClass]['methods'][$methodName]['calledFrom'][$location] = true;
                }
            }
        }
    }

    private function processMethodCall($currentClass, $methodName, $file)
    {
        if ($this->isLaravelDynamicMethod($methodName)) {
            return;
        }

        foreach ($this->classMap as $className => $classInfo) {
            if ($className !== $currentClass &&
                isset($classInfo['methods'][$methodName])) {
                $location = $file . ':' . $methodName;
                $this->classMap[$className]['methods'][$methodName]['calledFrom'][$location] = true;
            }
        }
    }

    private function isLaravelDynamicMethod(string $methodName): bool
    {
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

        foreach ($dynamicPrefixes as $prefix) {
            if (strpos($methodName, $prefix) === 0) {
                return true;
            }
        }

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
        if (strpos($class, '\\') === 0) {
            return substr($class, 1);
        }

        if ($currentNamespace) {
            return $currentNamespace . '\\' . $class;
        }

        return $class;
    }
}
