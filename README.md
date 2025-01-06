# Laravel Tools by Gleman17

A Laravel package to simplify relationship management, model analysis, and table-to-model comparison. This package provides a set of Artisan commands for developers working with complex database relationships in Laravel applications.

## Features

- **Build Relationships**: Automatically generate Eloquent relationships between models.
- **Compare Tables with Models**: Detect database tables without corresponding models and optionally generate them.
- **List Models**: List all models in your Laravel project.
- **Remove Relationships**: Remove Eloquent relationships from models based on configuration.

## Installation

### Requirements
- Laravel 8 or higher
- PHP 8.1 or higher

### Install via Composer
To install this package in your Laravel project:

```bash
composer require gleman17/laravel-tools
```

### Publish Configuration (Optional)
You can publish the configuration file to customize command signatures:

```bash
php artisan vendor:publish --tag=laravel-tools-config
```

This will create a `config/laravel_tools.php` file where you can configure command signatures and other options.

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


