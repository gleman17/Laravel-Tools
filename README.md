# Laravel Tools

A Laravel package to simplify relationship management, model analysis, and table-to-model comparison. This package provides a set of Artisan commands for developers working with complex database relationships in Laravel applications.

Why would you need this?  Can't you just create relationships on your models by hand?

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

- **Build Relationships**: Automatically generate Eloquent relationships between models.
- **Compare Tables with Models**: Detect database tables without corresponding models and optionally generate them.
- **List Models**: List all models in your Laravel project.
- **Remove Relationships**: Remove Eloquent relationships from models based on configuration.

## Installation

### Requirements
- Laravel 11 or higher
- PHP 8.4.1 or higher
- staudenmeir/eloquent-has-many-deep
- staudenmeir/belongs-to-through

These requirements have not been verified.  The package may work on lower levels.  
The staudenmeir packages are only required if you're building the multi-step relationships.  
The package itself has no dependency upon staudenmeir.

### Install via Composer
To install this package in your Laravel project:

```bash
composer require gleman17/laravel_tools
```

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


