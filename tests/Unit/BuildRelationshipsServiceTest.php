<?php

namespace Tests\Unit;

use Gleman17\LaravelTools\Services\BuildRelationshipsService;
use Gleman17\LaravelTools\Services\ModelService;
use Gleman17\LaravelTools\Services\RelationshipService;
use Gleman17\LaravelTools\Services\TableRelationshipAnalyzerService;
use Mockery;
use Tests\TestCase;

class BuildRelationshipsServiceTest extends TestCase
{
    private $analyzer;
    private $relationshipService;
    private $modelService;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = Mockery::mock(TableRelationshipAnalyzerService::class);
        $this->relationshipService = Mockery::mock(RelationshipService::class);
        $this->modelService = Mockery::mock(ModelService::class);

        $this->service = new BuildRelationshipsService(
            $this->analyzer,
            $this->relationshipService,
            $this->modelService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_validates_model_existence_when_building_relationships()
    {
        $this->modelService->shouldReceive('modelExists')
            ->with('NonExistentModel')
            ->once()
            ->andReturn(false);

        $messages = $this->service->build('NonExistentModel', 'AnotherModel');

        $this->assertEquals(['Model NonExistentModel does not exist.'], $messages);
    }

    /** @test */
    public function it_can_build_a_single_relationship()
    {
        $this->modelService->shouldReceive('modelExists')
            ->twice()
            ->andReturn(true);

        $this->analyzer->shouldReceive('analyze')->once();
        $this->analyzer->shouldReceive('generateRelationship')
            ->with('User', 'Post')
            ->once();
        $this->analyzer->shouldReceive('getMessages')
            ->once()
            ->andReturn(['Relationship created successfully']);

        $messages = $this->service->build('User', 'Post');

        $this->assertEquals(['Relationship created successfully'], $messages);
    }

    /** @test */
    public function it_can_build_all_relationships_for_a_model()
    {
        $this->modelService->shouldReceive('modelExists')
            ->once()
            ->andReturn(true);

        $this->analyzer->shouldReceive('analyze')->once();
        $this->analyzer->shouldReceive('findConnectedModels')
            ->with('User')
            ->once()
            ->andReturn(['Post', 'Comment']);

        $this->analyzer->shouldReceive('generateRelationship')
            ->twice();
        $this->analyzer->shouldReceive('getMessages')
            ->twice()
            ->andReturn(['Success']);

        $messages = $this->service->build('User', null, true);

        $this->assertEquals(['Success', 'Success'], $messages);
    }

    /** @test */
    public function it_handles_no_connected_models()
    {
        $this->modelService->shouldReceive('modelExists')
            ->once()
            ->andReturn(true);

        $this->analyzer->shouldReceive('analyze')->once();
        $this->analyzer->shouldReceive('findConnectedModels')
            ->with('User')
            ->once()
            ->andReturn([]);

        $messages = $this->service->build('User', null, true);

        $this->assertEquals(['No connected models found for User'], $messages);
    }

    /** @test */
    public function it_can_build_all_relationships()
    {
        $this->modelService->shouldReceive('getModelNames')
            ->once()
            ->andReturn(['User', 'Post']);

        $this->analyzer->shouldReceive('analyze')->once();
        $this->analyzer->shouldReceive('findConnectedModels')
            ->twice()
            ->andReturn(['Comment']);

        $this->analyzer->shouldReceive('generateRelationship')
            ->twice();
        $this->analyzer->shouldReceive('getMessages')
            ->twice()
            ->andReturn(['Success']);

        $messages = $this->service->build(null, null, true);

        $this->assertEquals(['Success', 'Success'], $messages);
    }

    /** @test */
    public function it_can_remove_a_single_relationship()
    {
        $this->modelService->shouldReceive('modelExists')
            ->twice()
            ->andReturn(true);

        $this->relationshipService->shouldReceive('getRelationshipName')
            ->twice()
            ->andReturn('posts', 'user');

        $this->relationshipService->shouldReceive('removeRelationshipFromModel')
            ->twice();

        $messages = $this->service->remove('User', 'Post');

        $this->assertEmpty($messages);
    }

    /** @test */
    public function it_can_remove_all_relationships_for_a_model()
    {
        $this->modelService->shouldReceive('modelExists')
            ->once()
            ->andReturn(true);

        $this->modelService->shouldReceive('getModelNames')
            ->once()
            ->andReturn(['User', 'Post', 'Comment']);

        $this->relationshipService->shouldReceive('getRelationshipName')
            ->times(4)
            ->andReturn('relationship');

        $this->relationshipService->shouldReceive('removeRelationshipFromModel')
            ->times(4);

        $messages = $this->service->remove('User', null, true);

        $this->assertEmpty($messages);
    }
}
