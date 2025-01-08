<?php
// ModelService.php
namespace Gleman17\LaravelTools\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Filesystem\Filesystem;

class ModelService
{
    protected $command;
    protected $filesystem;
    protected $logger;

    public function __construct($command=null, $filesystem = null, $logger = null)
    {
        $this->command = $command;
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->logger = $logger ?? Log::getFacadeRoot();
    }

    /**
     * Gets an array of fully qualified model class names.
     *
     * @return array<class-string<Model>>
     */
    public function getModelNames(): array
    {
        $modelsPath = app_path('Models');
        $models = [];

        foreach ($this->filesystem->allFiles($modelsPath) as $file) {
            $class = 'App\\Models\\' . $file->getFilenameWithoutExtension();
            if (class_exists($class)) {
                $models[] = $class;
            }
        }
        return $models;
    }

    public function modelExists(string $modelName): bool
    {
        $modelPath = app_path("Models/{$modelName}.php");
        return $this->filesystem->exists($modelPath);
    }
}
