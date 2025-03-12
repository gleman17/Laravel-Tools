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
    private function normalizeSql($query)
    {
        // Remove excessive whitespace and lowercase everything
        $query = strtolower(trim(preg_replace('/\s+/', ' ', $query)));

        // Normalize JOIN conditions by reordering conditions within ON clauses
        $query = preg_replace_callback('/(join\s+\w+\s+on\s+)(\w+)\s*=\s*(\w+)/i', function ($matches) {
            // Sort operands alphabetically to ensure consistent order
            $operands = [$matches[2], $matches[3]];
            sort($operands);
            return $matches[1] . "{$operands[0]} = {$operands[1]}";
        }, $query);

        return $query;
    }
    /** @test */
    public function it_can_get_the_involved_tables()
    {
        $aiQueryService = new AIQueryService();
        $expected = json_encode(['employees'], true);
        $answer = $aiQueryService->getQueryTables('how many employees are there?');
        $this->assertEquals($expected, json_encode($answer));
    }

    /** @test */
    public function it_can_get_the_involved_tables_with_synonym()
    {
        $aiQueryService = new AIQueryService();
        $expected = json_encode(['employees'], true);
        $answer = $aiQueryService->getQueryTables('how many staff are there?',['staff' => 'employees' ]);
        $this->assertEquals($expected, json_encode($answer));
    }

    /** @test */
    public function it_can_query_the_db_with_joins()
    {
        $expected = <<<SQL
SELECT c.id AS company_id, c.name AS company_name, COUNT(e.id) AS employee_count, COUNT(p.id) AS project_count
FROM companies c
    LEFT JOIN employees e ON e.company_id = c.id
    LEFT JOIN projects p ON p.company_id = c.id
GROUP BY c.id, c.name
SQL;

        $aiQueryService = new AIQueryService();
        $answer = $aiQueryService->getQuery('show me the number of employees and projects for each company.  the first '.
        'part of the select should be SELECT c.id AS company_id, c.name AS company_name, COUNT(e.id) AS employee_count, COUNT(p.id) AS project_count');
        info($expected);
        info($answer);
        $this->assertSame(cleanString($expected), cleanString($answer));
    }

    /** @test */
    public function it_can_query_the_db_with_questionable_synonym()
    {
        $expected = <<<SQL
SELECT users.id, users.name, users.email
FROM users
JOIN scam_check_users ON scam_check_users.user_id = users.id
WHERE users.is_admin = 1
SQL;

        $aiQueryService = new AIQueryService();
        $answer = $aiQueryService->getQuery('show me users id, name, and email who are admins that have scams', ['scams' => 'scam_checks']);
        $this->assertSame($this->normalizeSql($expected), $this->normalizeSql($answer));
    }

    /** @test */
    public function it_can_query_relationship_missing()
    {
        $aiQueryService = new AIQueryService();
        $expected = <<<SQL
SELECT users.id, users.name, users.email FROM users
JOIN posts ON posts.user_id = users.id
LEFT JOIN comments ON comments.post_id = posts.id
GROUP BY users.id
HAVING COUNT(comments.id) = 0
SQL;

        $answer = $aiQueryService->getQuery('show me users with their id, name, and email that have posts without any comments');
        $this->assertEquals($this->normalizeSql($expected), $this->normalizeSql($answer));
    }

    /** @test */
    public function it_can_query_relationship_existing()
    {
        $aiQueryService = new AIQueryService();
        $expected = <<<SQL
SELECT users.id, users.name, users.email
FROM users
JOIN comments ON comments.user_id = users.id
WHERE users.created_at >= NOW() - INTERVAL 7 DAY
SQL;

        $answer = $aiQueryService->getQuery('Show me all users with their id, name, and email that have been created in the last week that have added a comment');
        $this->assertEquals($this->normalizeSql($expected), $this->normalizeSql($answer));
    }


    /** @test */
    public function it_can_select_multiple_tables()
    {
        $aiQueryService = new AIQueryService();

        $answer = $aiQueryService->getQuery('Show me all users that have been created in the last week that have added a comment with the post and their comments');
        $this->assertStringContainsStringIgnoringCase('JOIN comments ON comments.user_id = users.id', $this->normalizeSql($answer), "SQL must LEFT JOIN comments on users.id.");
        $this->assertMatchesRegularExpression('/WHERE users\.created_at >= NOW\(\) - INTERVAL 7 DAY/i', $this->normalizeSql($answer), "SQL must filter users by created_at in the last 7 days.");
    }
}
