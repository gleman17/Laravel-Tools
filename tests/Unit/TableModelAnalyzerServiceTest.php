<?php

namespace Tests\Unit;

use Gleman17\LaravelTools\Services\DatabaseTableService;
use Gleman17\LaravelTools\Services\TableModelAnalyzerService;
use Tests\TestCase;

class TableModelAnalyzerServiceTest extends TestCase
{
    protected TableModelAnalyzerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TableModelAnalyzerService();
    }

    /** @test */
    public function it_can_get_model_table_names() : void
    {
        $model_table_names = $this->service->getModelTableNames();
        $this->assertContains('users', $model_table_names);
    }
}
