# Laravel Tools

Laravel Tools is a package designed to enhance developer productivity by providing a natural language query interface.  You can
describe the data you're looking for in plain english and it will convert that to an SQL query as well as an Eloquent query.
This package also provides tools for simplifying relationship management, model analysis, and table-to-model comparisons in 
Laravel applications.  It provides a suite of Artisan commands to assist developers in managing complex database relationships 
efficiently.

Here's how easy it is:
```php
use Gleman17\LaravelTools\Services\AIQueryService;

$aiQueryService = new AIQueryService();
$answer = $aiQueryService->getQuery('show me users that have posts without any comments');
```

Here's the sql that will be contained within $answer:

```sql
SELECT users.id, users.name, users.email 
FROM users
JOIN posts ON posts.user_id = users.id
LEFT JOIN comments ON comments.post_id = posts.id
GROUP BY users.id
HAVING COUNT(comments.id) = 0;
```
Why would you need this?  Can't you just dump the metadate from your database to a file, load that into an LLLM, and get
your query?  

Yes, you can do this for small databases, but with larger databases you may run into problems with costs (those
tokens aren't free) and the LLM might hallucinate some tables and relationships that it thinks should be there but actually aren't.

This package takes a different approach.  The key is to limit the amount of metadate the LLM needs to look at in order to formulate
the query.  It does this by analyzing the query to see what entities are described, then it builds a graph of the
database and returns the metadata showing the relationships between tables for only those tables that are required to perform 
the query.  For example, if if your query was "Show me all users that have been created in the last week that have added a comment" 
it would determine that to answer this you need the users, posts, and comments table.  It would provide the metadata for just
those tables to the LLM and let it refine the query further.

```sql
SELECT users.id, users.name, users.email, 
       comments.id AS comment_id, comments.content, comments.created_at AS comment_created_at, 
       posts.id AS post_id, posts.title AS post_title
FROM users
JOIN comments ON comments.user_id = users.id
JOIN posts ON posts.id = comments.post_id
WHERE users.created_at >= NOW() - INTERVAL 7 DAY;
```

Can't you just create relationships on your models by hand?

This package goes a bit further.  It adds commands that allow you to update the relationships on your models.
It builds a graph of the database structure to determine the connections between tables.  It uses the Laravel naming 
convention of [singular table name]_id to infer that a connection exists between the tables.

This can be quite useful if your relationships are complicated.  Just keeping the syntax
of these deep relationships in your head can be a bit challenging.

If a table has a direct relationship, it builds a HasMany relationship on the model.

If a table has a two-step relationship (it goes through a pivot or a single other related table),
then it will generate a HasManyThrough relationship.

If a table has N steps (where N is 3 or more), it generates a staudenmeir/eloquent-has-many-deep
relationship.


## Features

- **Natural language query**: Describe what you want and it returns SQL and Eloquent
- **Build Relationships**: Automatically generate Eloquent relationships between models.
- **Compare Tables with Models**: Detect database tables without corresponding models and optionally generate them.
- **List Models**: List all models in your Laravel project.
- **Remove Relationships**: Remove Eloquent relationships from models based on configuration.

## Installation

### Requirements
- Laravel 11 or higher
- PHP 8.3 or higher
- nikic/php-parser
- echolabsdev/prism
- greenlion/php-sql-parser
- staudenmeir/eloquent-has-many-deep
- staudenmeir/belongs-to-through

These requirements have not been verified.  The package may work on lower levels.  

You will need to configure Prism with your API key for a compatible LLM.  You can configure as many LLMs as you wish,
limited only by Prism's large list of supported LLMs.

The staudenmeir packages are only required if you're building the multi-step relationships.  
The package itself has no dependency upon staudenmeir.

### Install via Composer
To install this package in your Laravel project:

```bash
composer require gleman17/laravel_tools
```
Follow the instructions to configure Prism.

### Publish Configuration (Optional)
You can publish the configuration file to customize command signatures:

```bash
php artisan vendor:publish --tag=laravel-tools-config
```

This will create a `config/gleman17_laravel_tools.php` file where you can configure command signatures and other options.

The namespace for the commands is "tools" but you can modify this by
changing the config file:

```php
return [
    'command_signatures' => [
        'remove_relationships' => 'tools:remove-relationships',
        'build_relationships' => 'tools:build-relationships',
        'compare_tables_with_models' => 'tools:check-tables',
        'list_models' => 'tools:list-models',
    ],
];
```
Just change the command signatures to whatever you'd like them to be.  It will add the
parameters as it needs them.  This technique is discussed at https://medium.com/@gleman17/customize-the-signature-of-your-laravel-command-5c729ce156b0



## Available Commands

### 1. `tools:build-relationships`
#### Description:
Builds Eloquent relationships between specified models.

#### Usage:
```bash
php artisan tools:build-relationships {startModel?} {endModel?} {--all}
```

#### Options:
- `startModel` (optional): The name of the starting model.
- `endModel` (optional): The name of the ending model.
- `--all`: Build relationships for all models.

#### Example:
```bash
php artisan tools:build-relationships User Role
```

---

### 2. `tools:compare-tables`
#### Description:
Lists database tables without corresponding models and optionally generates missing models.

#### Usage:
```bash
php artisan tools:compare-tables {--make}
```

#### Options:
- `--make`: Automatically create missing models for detected tables.

#### Example:
```bash
php artisan tools:compare-tables --make
```

---

### 3. `tools:list-models`
#### Description:
Lists all models in your Laravel project.

#### Usage:
```bash
php artisan tools:list-models
```

#### Example:
```bash
php artisan tools:list-models
```

---

### 4. `tools:remove-relationships`
#### Description:
Removes Eloquent relationships between specified models.

#### Usage:
```bash
php artisan tools:remove-relationships {startModel?} {endModel?} {--all}
```

#### Options:
- `startModel` (optional): The name of the starting model.
- `endModel` (optional): The name of the ending model.
- `--all`: Remove relationships for all models.

#### Example:
```bash
php artisan tools:remove-relationships User Role
```

---
# Laravel Tools - Natural Language SQL Query Generator

This package provides a powerful natural language to SQL query converter for Laravel applications. It allows you to transform human-readable queries into optimized SQL, taking into account your database structure and relationships.

## Installation

You can install the package via composer:

```bash
composer require gleman17/laravel-tools
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Gleman17\LaravelTools\LaravelToolsServiceProvider"
```

In the published configuration file `config/gleman17_laravel_tools.php`, you can set your preferred AI model:

```php
return [
    'ai_model' => 'gpt-4-0-mini',  // or your preferred model
];
```

## Basic Usage

### Converting Natural Language to SQL

```php
use Gleman17\LaravelTools\Services\AIQueryService;

$queryService = new AIQueryService();

// Get SQL from natural language query
$query = "show me all users who have posted in the last month";
$sql = $queryService->getQuery($query);

// Execute the generated SQL
$results = DB::select($sql);
```

### Working with Table Synonyms

You can provide domain-specific synonyms to improve query accuracy:

```php
$synonyms = [
    'customer' => 'users',
    'article' => 'posts'
];

$query = "find all customers who have written articles";
$sql = $queryService->getQuery($query, $synonyms);
```

### Getting Query Details

The service provides methods to understand how queries are interpreted:

```php
// Get tables involved in the query
$tables = $queryService->getQueryTables($query);

// Get reasoning behind table selection
$tableReasoning = $queryService->getTablesReasoning();

// Get reasoning behind SQL generation
$queryReasoning = $queryService->getQueryReasoning();
```

### Additional Query Rules

You can provide additional rules to customize SQL generation:

```php
$additionalRules = "Always include soft delete checks in the where clause";
$sql = $queryService->getQuery($query, $synonyms, $additionalRules);
```

## Features

- Natural language to SQL conversion
- Automatic table relationship detection
- Support for table synonyms
- Handles pivot tables automatically
- Provides reasoning for query generation
- Configurable AI model selection
- Retry mechanism for API calls
- Smart column selection based on query context

## Best Practices

1. **Be Careful**: Be extraordinarily cautious about exposing this interface to users as it could be used to violate security. 
Even if you add rules such as "always add organization_id = 10 to a where clause" validate that the generated SQL cannot be used
to expose data.

2. **Use Synonyms**: If your domain uses specific terminology, provide synonyms to improve accuracy.

3. **Review Generated SQL**: Initially review generated SQL queries to ensure they match your expectations.

4. **Monitor API Usage**: Since the service uses AI models, be mindful of API usage and implement appropriate rate limiting.

## Error Handling

The service implements retry logic for API calls and returns null if the query generation fails after multiple attempts. It's recommended to implement appropriate error handling:

```php
$sql = $queryService->getQuery($query);
if ($sql === null) {
    // Handle the error case
    Log::error('Failed to generate SQL query');
    return false;
}
```

## Contributing
Feel free to submit issues or pull requests to improve this package.

## License
This package is licensed under the MIT License. See the `LICENSE` file for more information.

---

Happy coding! 🎉


