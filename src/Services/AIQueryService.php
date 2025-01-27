<?php

namespace Gleman17\LaravelTools\Services;
use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Exceptions\PrismException;
use EchoLabs\Prism\Facades\Tool;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Responses\TextResponse;
use Config;

class AIQueryService
{
    public function getQueryTables($query): array
    {
        $dbTablesJson = json_encode((new DatabaseTableService())->getDatabaseTables());

        $prompt = <<<PROMPT
Given this sql query, determine the tables that are involved in the query.
 Match terms in the query to the most relevant table names in the database,
 including synonyms.  Your response must only include table names.  If there is
 a synonym, use the table name and not the synonym.  Pick the most specific match
 when there are multiple matches.
Respond only with a JSON array of table names. Do not include any additional
text or formatting.
This is the query inside quotes: "$query"
This is the list of tables in the database: $dbTablesJson
PROMPT;
        info('getQueryTables');
        $response = $this->callLLM( null, $prompt);
        $tables = json_decode($response);
        info($tables);

        return $tables;
    }

    /**
     * @param int $check_type
     * @param $scam_text
     * @return TextResponse
     * @throws PrismException
     */
    public function getQuery(string $query): ?string
    {
        $dbTables = $this->getQueryTables($query);
        $analyzerService = new TableRelationshipAnalyzerService();

        $analyzerService->analyze();
        $connectedTables = $analyzerService->findConnectedTables($dbTables);
        $jsonStructure = $this->getJsonStructure($connectedTables);

        $graph = $analyzerService->getGraph();
        $filteredGraph = $this->getFilteredGraph($graph, $connectedTables);
        $graphJson = json_encode($filteredGraph);

        $systemPrompt = <<<PROMPT
You are a database, SQL, and Laravel expert. You are being asked to generate
SQL queries based on a user's description. Your responses must be valid SQL
queries only, without any additional text, formatting, or explanations.
This is the json structure of the database tables: $jsonStructure
This is the relationship graph of the database in json: $graphJson
Use the database structure and relationship graph to generate queries efficiently
and accurately. Assume the relationships in the graph are reliable and complete.
PROMPT;

        return $this->callLLM($systemPrompt, $query);
    }

    /**
     * @param string $systemPrompt
     * @param string $prompt
     * @return string|null
     */
    public function callLLM(?string $systemPrompt, string $prompt): ?string
    {
        $retryAttempts = 3;
        $retryDelay = 5;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                // Generate the response
                $prism = Prism::text()
                    ->using(Provider::OpenAI, 'gpt-4o-mini');

                if ($systemPrompt !== null) {
                    $prism = $prism->withSystemPrompt($systemPrompt);
                }

                $prism = $prism
                    ->withPrompt($prompt)
                    ->withClientOptions(['timeout' => 30])
                    ->generate();
            } catch (Exception $e) {
                if ($attempt === $retryAttempts) {
                    return null;
                }
                sleep($retryDelay);
            }
        }
        return $prism->text;
    }

    /**
     * @param string $query
     * @return false|string
     */
    public function getJsonStructure(array $dbTables): string|false
    {
        $structure = [];

        foreach (array_unique($dbTables, SORT_STRING) as $table) {
            $structure[] = [
                'table' => $table,
                'columns' => (new DatabaseTableService())->getTableColumns($table)
            ];
        }

        return json_encode($structure);
    }

    /**
     * Returns a subgraph containing only the specified tables and their connections
     *
     * @param array<string> $tables List of table names to include
     * @return array<string, array<string>> Filtered adjacency list
     */
    public function getFilteredGraph(array $graph, array $tables): array
    {
        $filteredGraph = [];

        foreach ($tables as $table) {
            if (isset($graph[$table])) {
                $filteredGraph[$table] = array_intersect_key(
                    $graph[$table],
                    array_flip($tables)
                );
            }
        }

        return $filteredGraph;
    }
}
