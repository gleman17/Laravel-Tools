<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Gleman17\LaravelTools\Services\RelationshipService;
use Gleman17\LaravelTools\Services\TableRelationshipAnalyzerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class RelationshipServiceTest extends TestCase
{
    private RelationshipService $service;
    private TableRelationshipAnalyzerService $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test database
        $this->setUpTestDatabase();

        // Create service instances
        $this->analyzer = new TableRelationshipAnalyzerService();
        $this->service = new RelationshipService();

        // Analyze database relationships
        $this->analyzer->analyze();
    }

    private function setUpTestDatabase(): void
    {
        // Create test tables if they don't exist
        if (!Schema::hasTable('posts')) {
            Schema::create('posts', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('title');
                $table->text('content');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('comments')) {
            Schema::create('comments', function ($table) {
                $table->id();
                $table->foreignId('post_id')->constrained();
                $table->foreignId('user_id')->constrained();
                $table->text('content');
                $table->timestamps();
            });
        }

        if (User::whereEmail('admin@example.com')->doesntExist()) {
            DB::table('users')->insert([
                ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => bcrypt('secret')],
                ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => bcrypt('secret')],
                ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret')],
            ]);

            DB::table('posts')->insert([
                ['user_id' => User::first()->id, 'title' => 'First Post', 'content' => 'Content 1'],
                ['user_id' => User::skip(1)->first()->id, 'title' => 'Second Post', 'content' => 'Content 2']
            ]);

            DB::table('comments')->insert([
                ['post_id' => User::first()->id, 'user_id' => 2, 'content' => 'Great post!'],
                ['post_id' => User::skip(1)->first()->id, 'user_id' => 1, 'content' => 'Nice work!']
            ]);
        }
    }

    public function test_simple_select_conversion()
    {
        $sql = "SELECT * FROM users WHERE active = 1";
        $expected = "App\Models\User::query()\n    ->where('active', '=', 1)";

        $result = $this->service->sqlToEloquent($sql);
        $this->assertEquals($expected, $result);
    }

    public function test_select_specific_fields()
    {
        $sql = "SELECT name, email FROM users";
        $expected = "App\Models\User::query()\n    ->select('name', 'email')";

        $result = $this->service->sqlToEloquent($sql);
        $this->assertEquals($expected, $result);
    }

    public function test_simple_join_conversion()
    {
        $sql = "SELECT users.*, posts.title FROM users JOIN posts ON users.id = posts.user_id";
        $expected = cleanString("App\Models\User::query()\n    ->with('posts')\n    ->select('users.*', 'posts.title')");
info('expected: '. $expected);
        $result = cleanString($this->service->sqlToEloquent($sql));
        info('result: '. $result);
        $this->assertEquals($expected, $result);
    }

    public function test_multiple_joins_conversion()
    {
        $sql = "SELECT users.name, posts.title, comments.content
                FROM users
                JOIN posts ON users.id = posts.user_id
                JOIN comments ON posts.id = comments.post_id";

        $expected = "App\Models\User::query()\n    ->with('posts')\n    ->with('posts.comments')\n    ->select('users.name', 'posts.title', 'comments.content')";

        $result = $this->service->sqlToEloquent($sql);
        $this->assertEquals($expected, $result);
    }

    public function test_where_clause_with_multiple_conditions()
    {
        $sql = "SELECT * FROM users WHERE active = 1 AND name LIKE '%Test%'";
        $expected = "App\Models\User::query()\n    ->where('active', '=', 1)\n    ->where('name', 'LIKE', '%Test%')";

        $result = $this->service->sqlToEloquent($sql);
        $this->assertEquals($expected, $result);
    }

    public function test_order_by_clause()
    {
        $sql = "SELECT * FROM users ORDER BY created_at DESC, name ASC";
        $expected = "App\Models\User::query()\n    ->orderBy('created_at', 'DESC')\n    ->orderBy('name', 'ASC')";

        $result = $this->service->sqlToEloquent($sql);
        $this->assertEquals($expected, $result);
    }

    public function test_group_by_clause()
    {
        $sql = "SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id";
        $expected = "App\Models\Post::query()\n    ->select('user_id', 'COUNT(*) as post_count')\n    ->groupBy('user_id')";

        $result = $this->service->sqlToEloquent($sql);
        $this->assertEquals($expected, $result);
    }

    public function test_complex_query_conversion()
    {
        $sql = "SELECT users.name, COUNT(posts.id) as post_count
                FROM users
                LEFT JOIN posts ON users.id = posts.user_id
                WHERE users.active = 1
                GROUP BY users.id
                HAVING post_count > 0
                ORDER BY post_count DESC";

        $expected = cleanString("App\Models\User::query()\n    ->with('posts')\n    ->select('users.name', 'COUNT(posts.id) as post_count')\n    ->where('users.active', '=', 1)\n    ->groupBy('users.id')\n    ->having('post_count', '>', 0)\n    ->orderBy('post_count', 'DESC')");

        $result = cleanString($this->service->sqlToEloquent($sql));
        info('expected: '. $expected);
        info('result: '. $result);
        $this->assertEquals($expected, $result);
    }

    public function test_invalid_sql_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->sqlToEloquent("INVALID SQL QUERY");
    }

    public function test_nonexistent_table_relationship()
    {
        $this->expectException(\RuntimeException::class);
        $sql = "SELECT * FROM users JOIN nonexistent_table ON users.id = nonexistent_table.user_id";
        $this->service->sqlToEloquent($sql);
    }

    protected function tearDown(): void
    {

        parent::tearDown();
    }
}
