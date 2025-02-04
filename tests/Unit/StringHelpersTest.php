<?php

namespace Tests\Unit;

use Tests\TestCase;
use gleman17\laravel_tools\Traits\HasEloquentStrings;

class StringHelpersTest extends TestCase
{
    use HasEloquentStrings;

    private string $indent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indent = str_repeat(' ', 4);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertEquals('', $this->prettyPrintEloquent(''));
    }

    public function test_single_statement_remains_unchanged(): void
    {
        $input = 'User::query()';
        $this->assertEquals($input, $this->prettyPrintEloquent($input));
    }

    public function test_basic_method_chaining(): void
    {
        $input = 'User::query()->where("active", true)->get()';
        $expected = <<<'EOT'
User::query()
    ->where("active", true)
    ->get()
EOT;
        $this->assertEquals($expected, $this->prettyPrintEloquent($input));
    }

    public function test_query_with_closure(): void
    {
        $input = 'User::query()->whereHas("posts", function($query) { $query->where("published", true); })->get()';
        $expected = <<<'EOT'
User::query()
    ->whereHas("posts", function($query) {
        $query->where("published", true);
    })
    ->get()
EOT;
        $result = $this->prettyPrintEloquent($input);
        info($expected);
        info($result);
        $this->assertEquals($expected, $result);
    }

    public function test_complex_query_with_multiple_closures(): void
    {
        $input = 'User::query()->whereHas("posts", function($query) { $query->where("published", true); })->join("profiles", "users.id", "=", "profiles.user_id")->where(function($query) { $query->where("active", true)->orWhere("admin", true); })->get()';
        $expected = <<<'EOT'
User::query()
    ->whereHas("posts", function($query) {
        $query->where("published", true);
    })
    ->join("profiles", "users.id", "=", "profiles.user_id")
    ->where(function($query) {
        $query->where("active", true)
        ->orWhere("admin", true);
    })
    ->get()
EOT;

        $result = $this->prettyPrintEloquent($input);
        info($expected);
        info($result);
        $this->assertEquals($expected, $result);
    }
}
