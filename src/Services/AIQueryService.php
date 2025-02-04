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
    private TableRelationshipAnalyzerService $analyzerService;
    private string $provider;

    public function __construct(?TableRelationshipAnalyzerService $analyzerService=null, ?string $provider=null)
    {
        $this->analyzerService = $analyzerService ?? new TableRelationshipAnalyzerService();
        $this->provider = $provider?? (Config('gleman17_laravel_tools.ai_model')?? 'gpt-4o-mini');
    }

    public function getQueryTables($query): array
    {
        $dbTablesJson = json_encode((new DatabaseTableService())->getDatabaseTables());

        $prompt = <<<PROMPT
Given this sql query, determine the tables that are involved in the query.
 Match terms in the query to the most relevant table names in the database,
 including synonyms.  Your response must only include table names.  If there is
 a synonym, use the table name and not the synonym.  Pick the most specific match
 when there are multiple matches.  Before returning the results, check that the table name
 exists in the list of tables in the database.
Respond only with a JSON array of table names. Do not include any additional
text or formatting.
This is the query inside quotes: "$query"
This is the list of tables in the database: $dbTablesJson
PROMPT;
        $result = $this->callLLM(null, $prompt);
        info($result);
        return json_decode($result);
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
        $this->analyzerService->analyze();
        $connectedTables = $this->analyzerService->findConnectedTables($dbTables);
        $jsonStructure = $this->getJsonStructure($connectedTables);

        $graph = $this->analyzerService->getGraph();
        $filteredGraph = $this->getFilteredGraph($graph, $connectedTables);
        $graphJson = json_encode($filteredGraph);

        $systemPrompt = <<<PROMPT
You are a database, SQL, and Laravel expert. Your responses must consist only of raw,
valid SQL SELECT queries, with no additional formatting, tags, explanations, or code block
delimiters such as triple backticks. Generate these SQL queries based solely on the provided
database structure and relationships. Do not provide any sql that will result in modification
of the data, such as update, delete, or insert.
This is the json structure of the database tables: $jsonStructure
This is the relationship graph of the database in json: $graphJson
Use the database structure and relationship graph to generate queries efficiently
and accurately. Assume the relationships in the graph are reliable and complete.
PROMPT;

        $llmResponse = $this->callLLM($systemPrompt, $query);
        return preg_replace('/^```sql\s*|\s*```\s*$/m', '', $llmResponse);
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
                    ->using(Provider::OpenAI, $this->provider);

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
