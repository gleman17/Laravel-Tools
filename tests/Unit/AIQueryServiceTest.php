<?php

namespace Tests\Unit;

use Gleman17\LaravelTools\Services\BuildRelationshipsService;
use Gleman17\LaravelTools\Services\ModelService;
use Gleman17\LaravelTools\Services\RelationshipService;
use Gleman17\LaravelTools\Services\TableRelationshipAnalyzerService;
use Gleman17\LaravelTools\Services\AIQueryService;

use Tests\TestCase;

class AIQueryServiceTest extends TestCase
{
    /** @test */
    public function it_can_get_the_involved_tables()
    {
        $aiQueryService = new AIQueryService();
        $answer = $aiQueryService->getQueryTables('how many employees are there?');
        $this->assertEquals('["employees"]', json_encode($answer));
    }

    /** @test */
    public function it_can_get_the_involved_tables_with_synonym()
    {
        $aiQueryService = new AIQueryService();
        $answer = $aiQueryService->getQueryTables('how many staff are there?');
        $this->assertEquals(['employees'], $answer);
    }

    /** @test */
    public function it_can_query_the_db_with_joins()
    {
        $expected = <<<SQL
SELECT c.id AS company_id, c.name AS company_name, COUNT(e.id) AS employee_count, COUNT(p.id) AS project_count FROM companies c LEFT JOIN employees e ON c.id = e.company_id LEFT JOIN projects p ON c.id = p.company_id GROUP BY c.id, c.name;
SQL;
        $aiQueryService = new AIQueryService();
        $answer = $aiQueryService->getQuery('show me the number of employees and projects for each company.  the first '.
        'part of the select should be SELECT c.id AS company_id, c.name AS company_name, COUNT(e.id) AS employee_count, COUNT(p.id) AS project_count');
        $this->assertEquals(cleanString($expected), cleanString($answer));
    }
}
