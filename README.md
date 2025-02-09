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

This will create a `config/laravel_tools.php` file where you can configure command signatures and other options.

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

## Configuration File
After publishing the configuration, you can modify the command signatures or disable specific commands in `config/laravel_tools.php`:

```php
return [
    'command_signatures' => [
        'build_relationships' => 'tools:build-relationships',
        'compare_tables' => 'tools:compare-tables',
        'list_models' => 'tools:list-models',
        'remove_relationships' => 'tools:remove-relationships',
    ],
];
```

## Contributing
Feel free to submit issues or pull requests to improve this package.

## License
This package is licensed under the MIT License. See the `LICENSE` file for more information.

---

Happy coding! 🎉


