<?php
declare(strict_types=1);

namespace Tests\Unit;
use PHPUnit\Framework\Attributes\Test;

use Gleman17\LaravelTools\Services\QueryBuilderService;
use Gleman17\LaravelTools\Services\DatabaseTableService;
use Gleman17\LaravelTools\Services\TableRelationshipAnalyzerService;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

use Mockery;
use InvalidArgumentException;

class QueryBuilderServiceTest extends TestCase
{
    protected $queryBuilder;
    protected $databaseService;
    protected $relationshipAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseService = Mockery::mock(DatabaseTableService::class);
        $this->relationshipAnalyzer = Mockery::mock(TableRelationshipAnalyzerService::class);

        // Mock the metadata
        $this->databaseService->shouldReceive('getMetadata')->andReturn([
            'users' => [
                'id' => ['type' => 'bigint', 'nullable' => false],
                'name' => ['type' => 'varchar', 'nullable' => false],
                'created_at' => ['type' => 'timestamp', 'nullable' => true],
            ],
            'posts' => [
                'id' => ['type' => 'bigint', 'nullable' => false],
                'user_id' => ['type' => 'bigint', 'nullable' => false],
                'title' => ['type' => 'varchar', 'nullable' => false],
                'created_at' => ['type' => 'timestamp', 'nullable' => true],
            ],
            'comments' => [
                'id' => ['type' => 'bigint', 'nullable' => false],
                'post_id' => ['type' => 'bigint', 'nullable' => false],
                'user_id' => ['type' => 'bigint', 'nullable' => false],
                'content' => ['type' => 'text', 'nullable' => false],
                'created_at' => ['type' => 'timestamp', 'nullable' => true],
            ]
        ]);

        // Mock the DatabaseTableService methods
        $this->databaseService->shouldReceive('tableToModelName')
            ->andReturnUsing(function ($table) {
                return ucfirst(rtrim($table, 's'));
            });

        $this->databaseService->shouldReceive('foreignKeyToRelationName')
            ->andReturnUsing(function ($foreignKey) {
                return str_replace('_id', '', $foreignKey);
            });

        $this->relationshipAnalyzer->shouldReceive('analyze')->once();
        $this->relationshipAnalyzer->shouldReceive('getGraph')
            ->andReturn([
                'users' => ['posts' => true, 'comments' => true],
                'posts' => ['users' => true, 'comments' => true],
                'comments' => ['users' => true, 'posts' => true]
            ]);

        $this->relationshipAnalyzer->shouldReceive('getColumnList')
            ->andReturn([
                'users' => ['posts' => 'user_id', 'comments' => 'user_id'],
                'posts' => ['users' => 'user_id', 'comments' => 'post_id'],
                'comments' => ['users' => 'user_id', 'posts' => 'post_id']
            ]);

        $this->queryBuilder = new QueryBuilderService(
            $this->databaseService,
            $this->relationshipAnalyzer
        );
    }

    #[Test]
    public function it_can_extract_main_entity_from_query()
    {
        $query = 'Show me all users';
        $result = $this->invokePrivateMethod($this->queryBuilder, 'identifyMainEntity', [$query]);
        $this->assertEquals('User', $result);
    }

    #[Test]
    public function it_can_detect_required_table_joins()
    {
        $query = 'show me all users with their posts';
        $joins = $this->invokePrivateMethod($this->queryBuilder, 'identifyJoins', [$query]);

        $this->assertCount(1, $joins);
        $join = $joins[0];
        $this->assertEquals('User', $join['from']);
        $this->assertEquals('Post', $join['to']);
        $this->assertEquals('user_id', $join['foreign_key']);
    }

    #[Test]
    public function it_can_handle_reverse_relationship_queries()
    {
        $query = 'show me posts with their users';
        $joins = $this->invokePrivateMethod($this->queryBuilder, 'identifyJoins', [$query]);

        $this->assertCount(1, $joins);
        $join = $joins[0];
        $this->assertEquals('Post', $join['from']);
        $this->assertEquals('User', $join['to']);
        $this->assertEquals('user_id', $join['foreign_key']);
    }

    #[Test]
    public function it_can_handle_multiple_joins()
    {
        $query = 'show me posts with their users and comments';
        $joins = $this->invokePrivateMethod($this->queryBuilder, 'identifyJoins', [$query]);

        $this->assertCount(2, $joins);
        $this->assertEquals('Post', $joins[0]['from']);
        $this->assertEquals('User', $joins[0]['to']);
        $this->assertEquals('Post', $joins[1]['from']);
        $this->assertEquals('Comment', $joins[1]['to']);
    }

    #[Test]
    public function it_can_parse_numeric_conditions()
    {
        $query = 'Show users with more than 5 posts';
        $conditions = $this->invokePrivateMethod($this->queryBuilder, 'identifyConditions', [$query]);

        $this->assertCount(1, $conditions);
        $this->assertEquals('numeric', $conditions[0]['type']);
        $this->assertEquals('>', $conditions[0]['operator']);
        $this->assertEquals('5', $conditions[0]['value']);
    }

    #[Test]
    public function it_can_parse_equality_conditions()
    {
        $query = 'Show posts where status equals published';
        $conditions = $this->invokePrivateMethod($this->queryBuilder, 'identifyConditions', [$query]);

        $this->assertCount(1, $conditions);
        $this->assertEquals('equality', $conditions[0]['type']);
        $this->assertEquals('=', $conditions[0]['operator']);
        $this->assertEquals('published', $conditions[0]['value']);
    }

    #[Test]
    public function it_can_handle_multiple_conditions()
    {
        $description = 'Show users with more than 5 posts and less than 10 comments';
        $conditions = $this->invokePrivateMethod($this->queryBuilder, 'identifyConditions', [$description]);

        $this->assertCount(2, $conditions);
        $moreCondition = collect($conditions)->firstWhere('operator', '>');
        $lessCondition = collect($conditions)->firstWhere('operator', '<');

        $this->assertNotNull($moreCondition, 'Should find a "more than" condition');
        $this->assertNotNull($lessCondition, 'Should find a "less than" condition');
        $this->assertEquals('5', $moreCondition['value']);
        $this->assertEquals('10', $lessCondition['value']);
    }

    #[Test]
    public function it_can_detect_multiple_aggregation_functions()
    {
        $query = 'Count the number of posts and average likes per post';
        $aggregations = $this->invokePrivateMethod($this->queryBuilder, 'identifyAggregations', [$query]);

        $this->assertCount(2, $aggregations);

        $this->assertEquals('count', $aggregations[0]['type']);
        $this->assertEquals('count', $aggregations[0]['function']);
        $this->assertEquals('posts', $aggregations[0]['table']);

        $this->assertEquals('avg', $aggregations[1]['type']);
        $this->assertEquals('avg', $aggregations[1]['function']);
        $this->assertEquals('posts', $aggregations[1]['table']);
    }

    #[Test]
    public function it_can_parse_complex_time_constraints()
    {
        $query = 'Show posts from last month and this week';
        $constraints = $this->invokePrivateMethod($this->queryBuilder, 'identifyTimeConstraints', [$query]);

        $this->assertCount(2, $constraints);
        $this->assertEquals('last month', $constraints[0]['timeframe']);
        $this->assertEquals('subMonth', $constraints[0]['method']);
        $this->assertEquals('this week', $constraints[1]['timeframe']);
        $this->assertEquals('startOfWeek', $constraints[1]['method']);
    }

    #[Test]
    public function it_requires_valid_table_relationships()
    {
        $query = 'Show me users with their nonexistent_tables';
        $joins = $this->invokePrivateMethod($this->queryBuilder, 'identifyJoins', [$query]);
        $this->assertEmpty($joins, 'Should not identify joins for invalid relationships');
    }

    #[Test]
    public function it_requires_proper_relationship_definition()
    {
        // Mock a broken relationship setup
        $this->relationshipAnalyzer = Mockery::mock(TableRelationshipAnalyzerService::class);
        $this->relationshipAnalyzer->shouldReceive('analyze');
        $this->relationshipAnalyzer->shouldReceive('getGraph')
            ->andReturn(['users' => []]);
        $this->relationshipAnalyzer->shouldReceive('getColumnList')
            ->andReturn([]);

        // Must recreate database service mock with proper expectations
        $this->databaseService = Mockery::mock(DatabaseTableService::class);
        $this->databaseService->shouldReceive('getMetadata')
            ->andReturn(['users' => []]);
        $this->databaseService->shouldReceive('tableToModelName')
            ->andReturnUsing(function ($table) {
                return ucfirst(rtrim($table, 's'));
            });
        $this->databaseService->shouldReceive('foreignKeyToRelationName')
            ->andReturnUsing(function ($foreignKey) {
                return str_replace('_id', '', $foreignKey);
            });

        $queryBuilder = new QueryBuilderService(
            $this->databaseService,
            $this->relationshipAnalyzer
        );

        // Single array with the string parameter
        $joins = $this->invokePrivateMethod($queryBuilder, 'identifyJoins', ['Show users with posts']);
        $this->assertEmpty($joins, 'Should not identify joins when relationships are not properly defined');
    }

    #[Test]
    public function it_handles_missing_time_field_gracefully()
    {
        // Create fresh mocks with all required expectations
        $this->relationshipAnalyzer = Mockery::mock(TableRelationshipAnalyzerService::class);
        $this->relationshipAnalyzer->shouldReceive('analyze');
        $this->relationshipAnalyzer->shouldReceive('getGraph')
            ->andReturn(['users' => []]);
        $this->relationshipAnalyzer->shouldReceive('getColumnList')
            ->andReturn([]);

        $this->databaseService = Mockery::mock(DatabaseTableService::class);
        $this->databaseService->shouldReceive('getMetadata')
            ->andReturn([
                'users' => [
                    'id' => ['type' => 'bigint', 'nullable' => false],
                    'name' => ['type' => 'varchar', 'nullable' => false]
                ]
            ]);
        // Add these required mock expectations
        $this->databaseService->shouldReceive('tableToModelName')
            ->andReturnUsing(function ($table) {
                return ucfirst(rtrim($table, 's'));
            });
        $this->databaseService->shouldReceive('foreignKeyToRelationName')
            ->andReturnUsing(function ($foreignKey) {
                return str_replace('_id', '', $foreignKey);
            });

        $queryBuilder = new QueryBuilderService(
            $this->databaseService,
            $this->relationshipAnalyzer
        );

        $query = 'Show users with posts';
        $constraints = $this->invokePrivateMethod($queryBuilder, 'identifyTimeConstraints', [$query]);
        $this->assertEmpty($constraints, 'Should not identify time constraints when time field is missing');
    }

    #[Test]
    public function it_can_build_complete_query_from_description()
    {
        $description = 'Show posts with more than 10 comments by users from this month';
        $query = $this->queryBuilder->buildQueryFromDescription($description);

        $sql = $query->toSql();
        $this->assertStringContainsString('join', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('created_at', $sql);
        $this->assertStringContainsString('count(*)', $sql);
    }



    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    #[Test]
    public function it_can_generate_eloquent_query_code_for_simple_query()
    {
        $description = 'Show all users';
        $code = $this->queryBuilder->generateEloquentQueryCode($description);

        $expectedCode = <<<'CODE'
$query = App\Models\User::query();
CODE;

        $this->assertStringContainsString($expectedCode, $code);
    }

    #[Test]
    public function it_can_generate_eloquent_query_code_with_joins()
    {
        $description = 'Show users with their posts';
        $code = $this->queryBuilder->generateEloquentQueryCode($description);

        $expectedCode = <<<'CODE'
$query = App\Models\User::query();
$query->join('posts', 'users.id', '=', 'posts.user_id');
CODE;

        $this->assertStringContainsString($expectedCode, $code);
    }

    #[Test]
    public function it_can_generate_code_with_conditions_and_time_constraints()
    {
        $description = 'Show users with more than 5 posts created this month';
        $code = $this->queryBuilder->generateEloquentQueryCode($description);

        $expectedCode = <<<'CODE'
$query = App\Models\User::query();
$query->join('posts', 'users.id', '=', 'posts.user_id');
$query->having(DB::raw('count(*)'), '>', 5);
$query->where('users.created_at', '>=', now()->startOfMonth());
CODE;

        $this->assertStringContainsString($expectedCode, $code);
    }


    #[Test]
    public function it_handles_invalid_descriptions_gracefully()
    {
        $description = 'Invalid description with no matching entities';
        $this->expectException(\InvalidArgumentException::class);
        $this->queryBuilder->generateEloquentQueryCode($description);
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
