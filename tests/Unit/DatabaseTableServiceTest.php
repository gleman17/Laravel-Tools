<?php

namespace Tests\Unit;
use PHPUnit\Framework\Attributes\Test;

use Gleman17\LaravelTools\Services\DatabaseTableService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;

class DatabaseTableServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DatabaseTableService();
    }

    public function test_get_database_tables_mysql()
    {
        // Mock DB facade
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('mysql');

        // Mock the SHOW TABLES query result
        $mockTables = [
            (object)['Tables_in_database' => 'users'],
            (object)['Tables_in_database' => 'posts'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with('SHOW TABLES')
            ->andReturn($mockTables);

        $result = $this->service->getDatabaseTables();

        $this->assertEquals(['users', 'posts'], $result);
    }

    public function test_get_database_tables_sqlite()
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('sqlite');

        $mockTables = [
            (object)['name' => 'users'],
            (object)['name' => 'posts'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("SELECT name FROM sqlite_master WHERE type='table'")
            ->andReturn($mockTables);

        $result = $this->service->getDatabaseTables();

        $this->assertEquals(['users', 'posts'], $result);
    }

    public function test_get_database_tables_pgsql()
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('pgsql');

        $mockTables = [
            (object)['tablename' => 'users'],
            (object)['tablename' => 'posts'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
            ->andReturn($mockTables);

        $result = $this->service->getDatabaseTables();

        $this->assertEquals(['users', 'posts'], $result);
    }

    public function test_get_database_tables_sqlsrv()
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('sqlsrv');

        $mockTables = [
            (object)['TABLE_NAME' => 'users'],
            (object)['TABLE_NAME' => 'posts'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'")
            ->andReturn($mockTables);

        $result = $this->service->getDatabaseTables();

        $this->assertEquals(['users', 'posts'], $result);
    }

    public function test_get_table_columns_mysql()
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('mysql');

        $mockColumns = [
            (object)[
                'Field' => 'id',
                'Type' => 'bigint(20)',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
            ],
            (object)[
                'Field' => 'name',
                'Type' => 'varchar(255)',
                'Null' => 'YES',
                'Key' => '',
                'Default' => null,
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with('SHOW COLUMNS FROM users')
            ->andReturn($mockColumns);

        $expected = [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'default' => null,
                'key' => 'PRI',
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => true,
                'default' => null,
                'key' => '',
            ],
        ];

        $result = $this->service->getTableColumns('users');

        $this->assertEquals($expected, $result);
    }

    public function test_get_table_columns_pgsql()
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('pgsql');

        $mockColumns = [
            (object)[
                'column_name' => 'id',
                'data_type' => 'integer',
                'is_nullable' => 'NO',
                'column_default' => "nextval('users_id_seq'::regclass)",
            ],
            (object)[
                'column_name' => 'name',
                'data_type' => 'character varying',
                'is_nullable' => 'YES',
                'column_default' => null,
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("
                    SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_name = ?
                    AND table_schema = 'public'
                ", ['users'])
            ->andReturn($mockColumns);

        $expected = [
            'id' => [
                'type' => 'integer',
                'nullable' => false,
                'default' => "nextval('users_id_seq'::regclass)",
            ],
            'name' => [
                'type' => 'character varying',
                'nullable' => true,
                'default' => null,
            ],
        ];

        $result = $this->service->getTableColumns('users');

        $this->assertEquals($expected, $result);
    }

    public function test_get_metadata_returns_all_tables_with_columns()
    {
        // Mock getting tables
        DB::shouldReceive('getDriverName')
            ->times(3)  // Once for tables, twice for columns
            ->andReturn('mysql');

        DB::shouldReceive('select')
            ->once()
            ->with('SHOW TABLES')
            ->andReturn([
                (object)['Tables_in_database' => 'users'],
                (object)['Tables_in_database' => 'posts'],
            ]);

        // Mock columns for users table
        DB::shouldReceive('select')
            ->once()
            ->with('SHOW COLUMNS FROM users')
            ->andReturn([
                (object)[
                    'Field' => 'id',
                    'Type' => 'bigint(20)',
                    'Null' => 'NO',
                    'Key' => 'PRI',
                    'Default' => null,
                ],
            ]);

        // Mock columns for posts table
        DB::shouldReceive('select')
            ->once()
            ->with('SHOW COLUMNS FROM posts')
            ->andReturn([
                (object)[
                    'Field' => 'id',
                    'Type' => 'bigint(20)',
                    'Null' => 'NO',
                    'Key' => 'PRI',
                    'Default' => null,
                ],
            ]);

        $result = $this->service->getMetadata();

        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('posts', $result);
        $this->assertArrayHasKey('id', $result['users']);
        $this->assertArrayHasKey('id', $result['posts']);
    }

    public function test_unsupported_driver_throws_exception()
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('mongodb');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database driver: mongodb');

        $this->service->getDatabaseTables();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
