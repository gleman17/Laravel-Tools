<?php

namespace Tests\Unit;

use Tests\TestCase;
use gleman17\laravel_tools\Traits\HasSqlStrings;

class SQLStringsTest extends TestCase
{
    use HasSqlStrings;

    public function test_empty_string_returns_empty(): void
    {
        $input = '';
        $expected = '';
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_single_select(): void
    {
        $input = 'SELECT id, name FROM users';
        $expected = <<<'EOT'
SELECT
    id,
    name
FROM users
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_select_with_where(): void
    {
        $input = 'SELECT id, name FROM users WHERE active = 1';
        $expected = <<<'EOT'
SELECT
    id,
    name
FROM users
WHERE
        active = 1
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_select_with_multiple_conditions(): void
    {
        $input = 'SELECT id, name FROM users WHERE active = 1 AND role = "admin" OR level > 5';
        $expected = <<<'EOT'
SELECT
    id,
    name
FROM users
WHERE
        active = 1
        AND role = "admin"
        OR level > 5
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_select_with_join(): void
    {
        $input = 'SELECT users.id, users.name, posts.title FROM users INNER JOIN posts ON users.id = posts.user_id';
        $expected = <<<'EOT'
SELECT
    users.id,
    users.name,
    posts.title
FROM users
INNER JOIN posts ON users.id = posts.user_id
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_complex_query_with_multiple_joins(): void
    {
        $input = 'SELECT users.id, users.name, posts.title, comments.body FROM users LEFT JOIN posts ON users.id = posts.user_id LEFT JOIN comments ON posts.id = comments.post_id WHERE users.active = 1 AND (posts.published = 1 OR posts.user_id = 1)';
        $expected = <<<'EOT'
SELECT
    users.id,
    users.name,
    posts.title,
    comments.body
FROM users
LEFT JOIN posts ON users.id = posts.user_id
LEFT JOIN comments ON posts.id = comments.post_id
WHERE
        users.active = 1
        AND (posts.published = 1
        OR posts.user_id = 1)
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_query_with_group_by_and_having(): void
    {
        $input = 'SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id HAVING post_count > 5';
        $expected = <<<'EOT'
SELECT
    user_id,
    COUNT(*) as post_count
FROM posts
GROUP BY user_id
HAVING post_count > 5
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_query_with_order_and_limit(): void
    {
        $input = 'SELECT id, name FROM users ORDER BY name ASC LIMIT 10 OFFSET 20';
        $expected = <<<'EOT'
SELECT
    id,
    name
FROM users
ORDER BY name ASC
LIMIT 10
OFFSET 20
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_handles_extra_whitespace(): void
    {
        $input = 'SELECT   id,    name   FROM    users   WHERE   active   =   1';
        $expected = <<<'EOT'
SELECT
    id,
    name
FROM users
WHERE
        active = 1
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_single_column_select(): void
    {
        $input = 'SELECT name FROM users';
        $expected = <<<'EOT'
SELECT name
FROM users
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }

    public function test_nested_conditions(): void
    {
        $input = 'SELECT id, name FROM users WHERE (active = 1 AND role = "admin") OR (level > 5 AND verified = 1)';
        $expected = <<<'EOT'
SELECT
    id,
    name
FROM users
WHERE
        (active = 1
        AND role = "admin")
        OR (level > 5
        AND verified = 1)
EOT;
        $this->assertEquals($expected, $this->prettyPrintSQL($input));
    }
}
