<?php

namespace Tests\Unit;
use PHPUnit\Framework\Attributes\Test;

use Gleman17\LaravelTools\Services\GenerateRelationship\GenerateRelationshipService;
use Gleman17\LaravelTools\Services\ModelGeneratorService;
use Gleman17\LaravelTools\Services\RelationshipService;
use Gleman17\LaravelTools\Services\TableRelationshipAnalyzerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TableRelationAnalyzerServiceTest extends TestCase
{
    private TableRelationshipAnalyzerService $service;
    private RelationshipService $relationshipService;
    private GenerateRelationshipService $generateRelationshipService;
    private ModelGeneratorService $modelGeneratorService;

    #[Test]
    public function it_can_get_the_path_between_two_tables(): void
    {

        $path = $this->service->findPath('posts', 'users');
        $this->assertCount(2, $path);
    }

    #[Test]
    public function it_can_generate_models(): void
    {
        $this->modelGeneratorService
            ->shouldReceive('ensureModelExists')
            ->with('Post')
            ->once();

        $this->service->generateRelationship('Post', 'User');

        $this->assertTrue(true); // Test should focus on interactions
    }

    #[Test]
    public function it_can_get_the_graph(): void
    {
        $this->assertArrayHasKey('posts', $this->service->getGraph());
    }

    #[Test]
    public function it_handles_missing_models(): void
    {
        $this->modelGeneratorService
            ->shouldReceive('ensureModelExists')
            ->andThrow(new \Exception('Model not found'));

        $this->service->generateRelationship('MissingModel', 'Country');
        $messages = $this->service->getMessages();
        $this->assertContains('MissingModel model path does not exist.', $messages);
    }

    protected function setUp(): void
    {
        parent::setUp();

// Mock external dependencies
        $this->mockFileFacade();
        $this->mockSchemaFacade();

// Mock services
        $this->generateRelationshipService = Mockery::mock(GenerateRelationshipService::class);
        $this->modelGeneratorService = Mockery::mock(ModelGeneratorService::class);
        $this->relationshipService = Mockery::mock(RelationshipService::class);

// Initialize the service with mocked dependencies
        $this->service = new TableRelationshipAnalyzerService(
            $this->generateRelationshipService,
            $this->modelGeneratorService
        );

        $this->service->setGraph(
            [
                'users' => [
                    'posts' => 1, // Indicates a relationship from `users` to `posts`.
                ],
                'posts' => [
                    'users' => 1, // Indicates a relationship from `posts` to `users`.
                ],
            ]
        );
        $this->service->setColumnList(
            [
                'posts' => [
                    'users' => 'user_id', // `posts` table links to `users` via `user_id`.
                ],
                'users' => [
                    // No foreign key in `users` for `posts`, but reverse mapping exists in `adjacencyList`.
                ],
            ]
        );
    }

    #[Test]
    public function it_returns_connected_tables_for_a_model_name()
    {
        // Arrange: Mock adjacency list
        $service = new TableRelationshipAnalyzerService();
        $mockAdjacencyList = [
            'users' => ['posts' => 1, 'roles' => 1],
            'posts' => ['users' => 1, 'comments' => 1],
            'roles' => ['users' => 1],
            'comments' => ['posts' => 1],
        ];
        $service->setGraph($mockAdjacencyList);

        // Act: Call the method to get connected tables
        $result = $service->getConnectedTables('User');

        // Assert: Verify the expected connected tables
        $this->assertEquals(['posts', 'roles'], $result);
    }

    private function mockFileFacade(): void
    {
        File::shouldReceive('exists')
            ->andReturn(false);

        File::shouldReceive('put')
            ->andReturn(true);

        File::shouldReceive('get')
            ->andReturn('');
    }

    private function mockSchemaFacade(): void
    {
        Schema::shouldReceive('getColumnListing')
            ->andReturn(['id', 'user_id', 'post_id']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
