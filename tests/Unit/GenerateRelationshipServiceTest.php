<?php

namespace Tests\Unit\Services\GenerateRelationship;
use PHPUnit\Framework\Attributes\Test;

use Gleman17\LaravelTools\Services\GenerateRelationship\GenerateRelationshipService;
use Gleman17\LaravelTools\Services\GenerateRelationship\OneStepRelationshipGenerator;
use Gleman17\LaravelTools\Services\GenerateRelationship\TwoStepRelationshipGenerator;
use Gleman17\LaravelTools\Services\GenerateRelationship\DeepRelationshipGenerator;
use Tests\TestCase;
use Mockery;

class GenerateRelationshipServiceTest extends TestCase
{
    private $oneStepGenerator;
    private $twoStepGenerator;
    private $deepGenerator;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oneStepGenerator = Mockery::mock(OneStepRelationshipGenerator::class);
        $this->twoStepGenerator = Mockery::mock(TwoStepRelationshipGenerator::class);
        $this->deepGenerator = Mockery::mock(DeepRelationshipGenerator::class);

        $this->service = new GenerateRelationshipService(
            $this->oneStepGenerator,
            $this->twoStepGenerator,
            $this->deepGenerator
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_one_step_relationship()
    {
        $relationshipName = 'posts';
        $relatedModelClass = 'App\\Models\\Post';
        $resolvedPath = [['table' => 'posts', 'foreign_key' => 'user_id']];
        $reverse = false;
        $expectedMethod = 'public function posts() { return $this->hasMany(Post::class); }';

        $this->oneStepGenerator->shouldReceive('generate')
            ->with($relationshipName, $relatedModelClass, $resolvedPath, $reverse)
            ->once()
            ->andReturn($expectedMethod);

        $result = $this->service->generateRelationshipMethod(
            $relationshipName,
            $relatedModelClass,
            $reverse,
            $resolvedPath
        );

        $this->assertEquals($expectedMethod, $result);
    }

    #[Test]
    public function it_generates_two_step_relationship()
    {
        $relationshipName = 'postComments';
        $relatedModelClass = 'App\\Models\\Comment';
        $resolvedPath = [
            ['table' => 'posts', 'foreign_key' => 'user_id'],
            ['table' => 'comments', 'foreign_key' => 'post_id']
        ];
        $reverse = false;
        $expectedMethod = 'public function postComments() { return $this->hasManyThrough(Comment::class, Post::class); }';

        $this->twoStepGenerator->shouldReceive('generate')
            ->with($relationshipName, $relatedModelClass, $resolvedPath, $reverse)
            ->once()
            ->andReturn($expectedMethod);

        $result = $this->service->generateRelationshipMethod(
            $relationshipName,
            $relatedModelClass,
            $reverse,
            $resolvedPath
        );

        $this->assertEquals($expectedMethod, $result);
    }

    #[Test]
    public function it_generates_deep_relationship()
    {
        $relationshipName = 'postCommentLikes';
        $relatedModelClass = 'App\\Models\\Like';
        $resolvedPath = [
            ['table' => 'posts', 'foreign_key' => 'user_id'],
            ['table' => 'comments', 'foreign_key' => 'post_id'],
            ['table' => 'likes', 'foreign_key' => 'comment_id']
        ];
        $reverse = false;
        $expectedMethod = 'public function postCommentLikes() { /* deep relationship */ }';

        $this->deepGenerator->shouldReceive('generate')
            ->with($relationshipName, $relatedModelClass, $resolvedPath, $reverse)
            ->once()
            ->andReturn($expectedMethod);

        $result = $this->service->generateRelationshipMethod(
            $relationshipName,
            $relatedModelClass,
            $reverse,
            $resolvedPath
        );

        $this->assertEquals($expectedMethod, $result);
    }

    #[Test]
    public function it_handles_reversed_relationships()
    {
        $relationshipName = 'user';
        $relatedModelClass = 'App\\Models\\User';
        $resolvedPath = [['table' => 'posts', 'foreign_key' => 'user_id']];
        $reverse = true;
        $expectedMethod = 'public function user() { return $this->belongsTo(User::class); }';

        $this->oneStepGenerator->shouldReceive('generate')
            ->with($relationshipName, $relatedModelClass, $resolvedPath, $reverse)
            ->once()
            ->andReturn($expectedMethod);

        $result = $this->service->generateRelationshipMethod(
            $relationshipName,
            $relatedModelClass,
            $reverse,
            $resolvedPath
        );

        $this->assertEquals($expectedMethod, $result);
    }

    #[Test]
    public function it_throws_exception_for_empty_path()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Resolved path cannot be empty');

        $this->service->generateRelationshipMethod(
            'posts',
            'App\\Models\\Post',
            false,
            []
        );
    }
}
