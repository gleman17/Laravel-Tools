<?php

namespace Gleman17\LaravelTools\Services;
use Gleman17\LaravelTools\Services\GenerateRelationship\GenerateRelationshipService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelGeneratorService
{
    /** @var string[] */
    protected array $errors = [];
    protected ?string $file;
    protected ?string $log;
    public function __construct(
        ?string $file = null,
        ?string $log = null
    )
    {
        $this->file = $file ?: File::class;
        $this->log = $log ?: Log::class;
    }
    /**
     * Ensure a model and its factory exist.
     *
     * @param string $modelName
     * @return string[] An array of error or success messages.
     */
    public function ensureModelExists(string $modelName): array
    {
        $this->errors = []; // Reset errors

        $modelPath = app_path('Models/' . $modelName . '.php');
        $factoryPath = database_path('factories/' . $modelName . 'Factory.php');
        $tableName = Str::snake(Str::plural($modelName));

        try {
            $this->checkDirectoryPermissions(app_path('Models'), 'Models directory');
            $this->checkDirectoryPermissions(database_path('factories'), 'Factories directory');

            if (!Schema::hasTable($tableName)) {
                $this->errors[] = "Table $tableName does not exist in the database.";
                return $this->errors;
            }

            [$fillable, $guarded] = $this->getModelProperties($tableName);
            $this->createModelIfNotExist($modelPath, $modelName, $fillable, $guarded);
            $this->createFactoryIfNotExist($factoryPath, $modelName, $fillable);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this->errors;
    }

    /**
     * Check if a directory is writable and add an error message if not.
     *
     * @param string $directory
     * @param string $description
     * @return void
     */
    protected function checkDirectoryPermissions(string $directory, string $description): void
    {
        try {
            if (!$this->file::isWritable($directory)) {
                throw new \RuntimeException("$description is not writable.");
            }
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    /**
     * Generate the content of the model file.
     *
     * @param string $modelName
     * @param string[] $fillable
     * @param string[] $guarded
     * @return string
     */
    protected function generateModelContent(string $modelName, array $fillable, array $guarded): string
    {
        $fillableArray = $this->formatArray($fillable);
        $guardedArray = $this->formatArray($guarded);

        return <<<EOT
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class $modelName extends Model
{
    use HasFactory;

    protected \$fillable = $fillableArray;

    protected \$guarded = $guardedArray;
}
EOT;
    }

    /**
     * Generate the content of the factory file.
     *
     * @param string $modelName
     * @param string[] $fillable
     * @return string
     */
    protected function generateFactoryContent(string $modelName, array $fillable): string
    {
        $factoryAttributes = collect($fillable)
            ->map(fn($column) => "'$column' => \$this->faker->word,")
            ->implode("\n".str_repeat(' ', 12));

        return <<<EOT
<?php

namespace Database\Factories;

use App\Models\\$modelName;
use Illuminate\Database\Eloquent\Factories\Factory;

class {$modelName}Factory extends Factory
{
    protected \$model = $modelName::class;

    public function definition()
    {
        return [
            $factoryAttributes
        ];
    }
}
EOT;
    }

    /**
     * Format an array into a properly indented PHP array string.
     *
     * @param string[] $array
     * @param int $ntabs
     * @return string
     */
    protected function formatArray(array $array, int $ntabs = 1): string
    {
        $formattedItems = array_map(fn($item) => str_repeat(' ', $ntabs * 8) . "'$item',", $array);
        $formattedString = implode("\n", $formattedItems);

        return "[\n$formattedString\n    ]";
    }

    /**
     * Check if the file exists, if not, create it by executing a closure.
     *
     * @param string $path
     * @param callable $createCallback
     * @return bool
     */
    protected function fileExistsOrCreate(string $path, callable $createCallback): bool
    {
        if (!$this->file::exists($path)) {
            try {
                $createCallback();
                return $this->file::exists($path);
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        return true; // Return true if the file already exists or was successfully created.
    }

    /**
     * @param $tableName
     * @return array
     */
    public function getModelProperties($tableName): array
    {
        $columns = Schema::getColumnListing($tableName);

        $excludedColumns = ['id', 'uuid', 'created_at', 'updated_at', 'deleted_at'];
        $fillable = array_values(array_diff($columns, $excludedColumns));
        $guarded = array_values(array_intersect($columns, $excludedColumns));
        return array($fillable, $guarded);
    }

    /**
     * @param $modelPath
     * @param string $modelName
     * @param mixed $fillable
     * @param mixed $guarded
     * @return void
     */
    public function createModelIfNotExist($modelPath, string $modelName, mixed $fillable, mixed $guarded): void
    {
        if (!$this->fileExistsOrCreate($modelPath, function () use ($modelName, $fillable, $guarded, $modelPath) {
            $modelContent = $this->generateModelContent($modelName, $fillable, $guarded);
            $this->file::put($modelPath, $modelContent);
            chmod($modelPath, 0777);
        })) {
            $this->errors[] = "Model $modelName already exists or failed to create.";
        }
    }

    /**
     * @param $factoryPath
     * @param string $modelName
     * @param mixed $fillable
     * @return void
     */
    public function createFactoryIfNotExist($factoryPath, string $modelName, mixed $fillable): void
    {
        if (!$this->fileExistsOrCreate($factoryPath, function () use ($modelName, $fillable, $factoryPath) {
            $factoryContent = $this->generateFactoryContent($modelName, $fillable);
            $this->file::put($factoryPath, $factoryContent);
            chmod($factoryPath, 0777);
        })) {
            $this->errors[] = "Factory for $modelName already exists or failed to create.";
        }
    }
}
