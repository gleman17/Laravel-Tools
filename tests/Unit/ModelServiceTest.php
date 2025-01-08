<?php

namespace Tests\Unit;

use Gleman17\LaravelTools\Services\ModelService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use Mockery;

class ModelServiceTest extends TestCase
{
    public function test_get_model_names_returns_model_classes()
    {
// Create mock files
        $file1 = Mockery::mock(SplFileInfo::class);
        $file1->shouldReceive('getFilenameWithoutExtension')
            ->once()
            ->andReturn('User');

        $file2 = Mockery::mock(SplFileInfo::class);
        $file2->shouldReceive('getFilenameWithoutExtension')
            ->once()
            ->andReturn('Post');

// Create filesystem mock
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('allFiles')
            ->once()
            ->with(app_path('Models'))
            ->andReturn([$file1, $file2]);

// Mock Log facade

// Create test class
        $modelService = new ModelService($filesystem);
        $result = $modelService->getModelNames();

        $this->assertEquals(['App\Models\User', 'App\Models\Post'], $result);
    }

    public function test_get_model_names_handles_empty_directory()
    {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('allFiles')
            ->once()
            ->with(app_path('Models'))
            ->andReturn([]);

        $modelService = new ModelService($filesystem);
        $result = $modelService->getModelNames();

        $this->assertEmpty($result);
    }

    public function test_get_model_names_handles_nonexistent_classes()
    {
        $file = Mockery::mock(SplFileInfo::class);
        $file->shouldReceive('getFilenameWithoutExtension')
            ->once()
            ->andReturn('NonExistentModel');

        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('allFiles')
            ->once()
            ->with(app_path('Models'))
            ->andReturn([$file]);

        $modelService = new ModelService($filesystem);
        $result = $modelService->getModelNames();

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_returns_true_when_model_exists()
    {
        $modelPath = app_path('Models/User.php');

        // Mock the filesystem to return true for the existence check
        $this->filesystem
            ->shouldReceive('exists')
            ->once()
            ->with($modelPath)
            ->andReturn(true);

        $result = $this->modelService->modelExists('User');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_model_does_not_exist()
    {
        $modelPath = app_path('Models/NonExistentModel.php');

        // Mock the filesystem to return false for the existence check
        $this->filesystem
            ->shouldReceive('exists')
            ->once()
            ->with($modelPath)
            ->andReturn(false);

        $result = $this->modelService->modelExists('NonExistentModel');

        $this->assertFalse($result);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->filesystem = Mockery::mock(Filesystem::class);
        $this->modelService = new ModelService(NULL, $this->filesystem);
        Log::swap(Mockery::mock(Logger::class)); // Mock the Log facade for testing purposes only.
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
