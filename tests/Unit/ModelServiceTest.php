<?php
namespace Tests\Unit;
use PHPUnit\Framework\Attributes\Test;

use Gleman17\LaravelTools\Services\ModelService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use Mockery;
use Illuminate\Log\Logger;

class ModelServiceTest extends TestCase
{
    protected $filesystem;
    protected $modelService;
    protected $testPath = '/test/path';

    public function setUp(): void
    {
        parent::setUp();
        $this->filesystem = Mockery::mock(Filesystem::class);
        $this->modelService = new ModelService(
            null,
            $this->filesystem,
            Mockery::mock(Logger::class),
            $this->testPath
        );
    }

    public function test_get_model_names_returns_model_classes()
    {
        $file1 = Mockery::mock(SplFileInfo::class);
        $file1->shouldReceive('getFilenameWithoutExtension')
            ->once()
            ->andReturn('User');

        $file2 = Mockery::mock(SplFileInfo::class);
        $file2->shouldReceive('getFilenameWithoutExtension')
            ->once()
            ->andReturn('Post');

        $this->filesystem->shouldReceive('allFiles')
            ->once()
            ->with($this->testPath . '/Models')
            ->andReturn([$file1, $file2]);

        $result = $this->modelService->getModelNames();

        $this->assertEquals(
            ['App\Models\Post', 'App\Models\User'],
            $result
        );
    }

    public function test_get_model_names_handles_empty_directory()
    {
        $this->filesystem->shouldReceive('allFiles')
            ->once()
            ->with($this->testPath . '/Models')
            ->andReturn([]);

        $result = $this->modelService->getModelNames();

        $this->assertEmpty($result);
    }

    public function test_get_model_names_handles_nonexistent_classes()
    {
        $file = Mockery::mock(SplFileInfo::class);
        $file->shouldReceive('getFilenameWithoutExtension')
            ->once()
            ->andReturn('NonExistentModel');

        $this->filesystem->shouldReceive('allFiles')
            ->once()
            ->with($this->testPath . '/Models')
            ->andReturn([$file]);

        $result = $this->modelService->getModelNames();

        $this->assertEquals(['App\Models\NonExistentModel'], $result);
    }

    #[Test]
    public function it_returns_true_when_model_exists()
    {
        $modelPath = $this->testPath . '/Models/User.php';

        $this->filesystem->shouldReceive('exists')
            ->once()
            ->with($modelPath)
            ->andReturn(true);

        $result = $this->modelService->modelExists('User');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_model_does_not_exist()
    {
        $modelPath = $this->testPath . '/Models/NonExistentModel.php';

        $this->filesystem->shouldReceive('exists')
            ->once()
            ->with($modelPath)
            ->andReturn(false);

        $result = $this->modelService->modelExists('NonExistentModel');

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
