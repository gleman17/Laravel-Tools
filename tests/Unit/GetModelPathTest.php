<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;
use Mockery;
use Gleman17\LaravelTools\Services\RelationshipService;

class GetModelPathTest extends TestCase
{
    private $fileSystem;
    private $logger;
    private $service;
    private $basePath = '/base/path';
    private $appPath = '/base/path/app';

    protected function setUp(): void
    {
        parent::setUp();
        $mocks = Mockery::getContainer()->getMocks();
        $this->fileSystem = Mockery::mock(Filesystem::class);
        $this->service = new RelationshipService(
            $this->fileSystem,
            null,
            $this->basePath,
            $this->appPath
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_gets_model_path_with_base_name()
    {
        $modelPath = $this->service->getModelPath('User');
        $this->assertEquals(
            '/base/path/app/Models/User.php',
            $modelPath
        );
    }

    /** @test */
    public function it_gets_model_path_with_partial_namespace()
    {
        $this->assertEquals(
            '/base/path/Models/User.php',
            $this->service->getModelPath('Models\User')
        );
    }

    /** @test */
    public function it_gets_model_path_with_full_namespace()
    {
        $this->assertEquals(
            '/base/path/app/Models/User.php',
            $this->service->getModelPath('App\Models\User')
        );
    }

    /** @test */
    public function it_gets_model_path_with_relative_path()
    {
        $this->assertEquals(
            '/base/path/some/other/path/MyModel.php',
            $this->service->getModelPath('some/other/path/MyModel')
        );
    }

    /** @test */
    public function it_gets_model_path_with_absolute_path()
    {
        $this->assertEquals(
            '/absolute/path/Model.php',
            $this->service->getModelPath('/absolute/path/Model')
        );
    }

    /** @test */
    public function it_removes_relationship_from_model()
    {
        $modelPath = '/base/path/app/Models/User.php';
        $originalContent = <<<'EOF'
<?php
class User {
    /**
     * Get posts
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
EOF;

        $expectedContent = <<<'EOF'
<?php
class User {
}
EOF;

        $this->fileSystem->shouldReceive('exists')->with($modelPath)->andReturn(true);
        $this->fileSystem->shouldReceive('get')->with($modelPath)->andReturn($originalContent);
        $this->fileSystem->shouldReceive('put')->with($modelPath, Mockery::any())->once();

        $result = $this->service->removeRelationshipFromModel('User', 'posts');
        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_removing_nonexistent_relationship()
    {
        $modelPath = '/base/path/app/Models/User.php';

        $this->fileSystem->shouldReceive('exists')->with($modelPath)->andReturn(true);
        $this->fileSystem->shouldReceive('get')->with($modelPath)->andReturn('<?php class User {}');

        $result = $this->service->removeRelationshipFromModel('User', 'nonexistent');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_checks_relationship_exists_in_model()
    {
        $modelPath = '/base/path/app/Models/User.php';
        $content = <<<'EOF'
<?php
class User {
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
EOF;

        $this->fileSystem->shouldReceive('exists')->with($modelPath)->andReturn(true);
        $this->fileSystem->shouldReceive('get')->with($modelPath)->andReturn($content);

        $this->assertTrue($this->service->relationshipExistsInModel('User', 'posts'));
        $this->assertFalse($this->service->relationshipExistsInModel('User', 'nonexistent'));
    }

    /** @test */
    public function it_finds_duplicate_relationships()
    {
        $modelPath = '/base/path/app/Models/User.php';
        $content = <<<'EOF'
<?php
class User {
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
EOF;

        $this->fileSystem->shouldReceive('exists')->with($modelPath)->andReturn(true);
        $this->fileSystem->shouldReceive('get')->with($modelPath)->andReturn($content);

        $duplicates = $this->service->checkDuplicateRelationships('User');
        $this->assertEquals(['posts'], $duplicates);
    }
}
