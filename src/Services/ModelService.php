<?php
// ModelService.php
namespace Gleman17\LaravelTools\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModelService
{
    protected $filesystem;
    protected $logger;

    public function __construct($filesystem = null, $logger = null)
    {
        $this->filesystem = $filesystem ?: File::getFacadeRoot();
        $this->logger = $logger ?: Log::getFacadeRoot();
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
}
