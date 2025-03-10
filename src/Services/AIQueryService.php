<?php

namespace Gleman17\LaravelTools\Services;
use PrismPHP\Prism\Prism;
use PrismPHP\Prism\Enums\Provider;
use PrismPHP\Prism\Exceptions\PrismException;
use PrismPHP\Prism\Facades\Tool;
use PrismPHP\Prism\Responses\TextResponse;
use Config;

class AIQueryService
{
    private TableRelationshipAnalyzerService $analyzerService;
    private string $provider;

    private string $queryReasoning;
    private string $tablesReasoning;

    /**
     * @param TableRelationshipAnalyzerService|null $analyzerService
     * @param string|null $provider
     */
    public function __construct(?TableRelationshipAnalyzerService $analyzerService=null, ?string $provider=null)
    {
        $this->analyzerService = $analyzerService ?? new TableRelationshipAnalyzerService();
        $this->provider = $provider?? (Config('gleman17_laravel_tools.ai_model')?? 'gpt-4o-mini');
    }

    /**
     * @param string $query
     * @param ?array $synonyms
     * @return array
     */
    public function getQueryTables(string $query, ?array $synonyms=[]): array
    {
        $dbTablesJson = json_encode((new DatabaseTableService())->getDatabaseTables());

        $prompt = <<<PROMPT
Given this natural language query, determine the tables that are involved in the query.
 Match terms in the query to the most relevant table names in the database,
 including synonyms.  Your response must only include table names.  If there is
 a synonym, use the table name and not the synonym.  If there are multiple matches between
 synonyms and tables, chose the most specific match.  Do not include any additional matches, just pick the best one.
 Before returning the results, check that the table name
 exists in the list of tables in the database.  Table names that are in the form of aaa_bbb where bbb
 is plural indicate a pivot table between aaa and bbb, so include both aaa, bbb, and the pivot table name
 in your response.
 Your output should be in the following json format:
 {"tables": array_of_table_names, "reasoning": "Your reasoning here"}
 Do not include any additional text or formatting.
 This is the query inside quotes: "$query"
 This is the list of tables in the database: $dbTablesJson
PROMPT;

        $prompt = $this->addSynonyms($synonyms, $prompt);
        $result = $this->callLLM(null, $prompt);
        $decodedResult = json_decode($result, True);
        $this->tablesReasoning = $decodedResult['reasoning'];
        return $decodedResult['tables'];
    }

    public function getTablesReasoning(): string
    {
        return $this->tablesReasoning;
    }

    /**
     * @param string $query
     * @param array|null $synonyms
     * @param string|null $additionalRules
     * @return string|null
     */
    public function getQuery(string $query, ?array $synonyms=[], ?string $additionalRules=null): ?string
    {
        $dbTables = $this->getQueryTables($query, $synonyms);

        $this->analyzerService->analyze();
        $connectedTables = $this->analyzerService->findConnectedTables($dbTables);
        $jsonStructure = $this->getJsonStructure($connectedTables);

        $graph = $this->analyzerService->getGraph();
        $filteredGraph = $this->getFilteredGraph($graph, $connectedTables);
        $graphJson = json_encode($filteredGraph);

        $systemPrompt = $this->getSystemPrompt($jsonStructure, $graphJson);
        $systemPrompt = $this->addSynonyms($synonyms, $systemPrompt);
        if ($additionalRules!== null) {
            $systemPrompt.= "\n\n". $additionalRules;
        }
        $systemPrompt.= "\n\nYour output must be in the following json format: {\"sql\": \"generated sql\", \"reasoning\": \"Your reasoning here\"}";

        $llmResponse = $this->callLLM($systemPrompt, $query);
        $decodedResult = json_decode($llmResponse, True);
        $this->queryReasoning = $decodedResult['reasoning'];

        return preg_replace('/^```sql\s*|\s*```\s*$/m', '', $decodedResult['sql']);
    }

    public function getQueryReasoning(): string
    {
        return $this->queryReasoning;
    }

    /**
     * @param string|null $systemPrompt
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
        return json_encode( array_map(function($table) {
            return [
                'table' => $table['table'],
                'columns' => array_keys($table['columns'])
            ];
        }, $structure));
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

    /**
     * @param array|null $synonyms
     * @param string $prompt
     * @return string
     */
    public function addSynonyms(?array $synonyms, string $prompt): string
    {
        if (count($synonyms) > 0) {
            $jsonSynonyms = json_encode($synonyms);
            $prompt .= <<<PROMPT
In addition to synonyms for tables that you may determine, the user has also provided a list of domain specific
synonyms in the form of a json array in the format of {"synonym":"table_name"}.  These synonyms should take
precedence over the synonyms that you detect in the natural language query.
This is the list of synonyms: $jsonSynonyms
PROMPT;
        }
        return $prompt;
    }

    /**
     * @param bool|string $jsonStructure
     * @param bool|string $graphJson
     * @return string
     */
    public function getSystemPrompt(bool|string $jsonStructure, bool|string $graphJson): string
    {
        $systemPrompt = <<<PROMPT
You are a database, SQL, and Laravel expert. Your responses must consist only of raw,
valid SQL SELECT queries, with no additional formatting, tags, explanations, or code block
delimiters such as triple backticks. Generate these SQL queries based solely on the provided
database structure and relationships.

Do not provide any sql that will result in modification of the data, such as update, delete, or insert.

Table names that are in the form of aaa_bbb where bbb is plural indicate a pivot table between aaa and bbb,
so if you are joining with a pivot table, ensure that the output includes both aaa, bbb, and the pivot table name.

When creating a join clause, the left side of the "on" clause should be the key of the table being joined.  A correct
example: JOIN posts ON posts.user_id = users.id.  This would be wrong: JOIN posts ON users.id = posts.user_id.

Determine which entities have been explicitly asked for when generating the columns to include in a select. If joins are
required to perform the query, determine if they asked for the joined entities in the columns to be returned.
For instance, "show users with posts" should generate a query that only retrieves the columns in the users table since
posts were not asked for but were only part of the limiting conditions.

If an entity has been asked for, include all of the columns mentioned for that entity, even if they are in a condition.

Do not imply any columns that are not mentioned in the query.

Do not imply any joins that are not provided in the relationship graph.

This is the json structure of the database tables: $jsonStructure

This is the relationship graph of the database in json: $graphJson

Use the database structure and relationship graph to generate queries efficiently
and accurately. Assume the relationships in the graph are reliable and complete. Verify that any
columns you use in your SQL queries are actually present in the database.
PROMPT;
        return $systemPrompt;
    }
}
