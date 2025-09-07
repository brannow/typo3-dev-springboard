# TYPO3 Dev Springboard

Ephemeral TYPO3 instances for extension development. Resets to clean state on every execution.

## Installation

```bash
composer require --dev brannow/typo3-dev-springboard
```
## Requirements

- PHP >=8.1
- ext-pdo
- typo3/minimal ^12.4 || ^13.4
- symfony/filesystem
- symfony/yaml

## Architecture Notes

- Features declare dependencies
- Builder resolves dependencies automatically
- Circular dependencies are detected
- Each execution starts fresh (unless `persistCache()`)
- SQLite database by default
- Topological sorting ensures correct execution order

## Minimal Start

```php
use Typo3DevSpringboard\{Builder, Typo3Version};

Builder::make(Typo3Version::TYPO3_13_LTS)
    ->installDir(__DIR__ . '/.Build')
    ->build()
    ->execute();
```

## Basic Frontend Page

```php
Builder::make(Typo3Version::TYPO3_13_LTS)
    ->installDir(__DIR__ . '/.Build')
    ->withRequest('/')
    ->addDatabasePageRecord([
        'uid' => 1,
        'title' => 'Homepage',
        'slug' => '/'
    ])
    ->addDatabaseContentRecord([
        'pid' => 1,
        'header' => 'Hello',
        'bodytext' => '<p>World</p>',
        'CType' => 'text'
    ])
    ->addDatabaseTypoScriptTemplate('
page = PAGE
page.10 = TEXT
page.10.value = Hello World
', 1)
    ->build()
    ->execute();
```

## Builder Methods

### Configuration

```php
->installDir(__DIR__ . '/.Build')  // Required: TYPO3 installation path
->persistCache()                    // Keep cache between executions
```

### Request

```php
->withRequest('/')
->withRequest('/page', 'example.local', 'POST', true)  // uri, domain, method, https
```

### Database

```php
->addDatabasePageRecord(['title' => 'Page', 'slug' => '/'])
->addDatabaseContentRecord(['pid' => 1, 'CType' => 'text'])
->addDatabaseTypoScriptTemplate($typoscript, $pageId, $root, $name)
```

### Site Configuration

```php
->setSiteRootPageId(1)
->addSiteLanguage(SiteLanguage::EN, SiteLanguage::DE)
->setSiteLanguage(SiteLanguage::DE, 1)  // Force language ID
->setSiteConfig([...])  // Complete override
```

### Execution

```php
->build()           // Prepare system
->execute()         // Run TYPO3
->execute(true)     // Return output instead of printing
```

## Custom Tables

### Quick Method - GenericTable

```php
$database = $builder->getFeature(\Typo3DevSpringboard\Feature\Database::class);

$genericTable = $database->getTable(\Typo3DevSpringboard\Feature\Database\GenericTable::class)
    ->addTableSchema('tx_extension_table', [
        'uid' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'pid' => 'INTEGER DEFAULT 0',
        'title' => 'TEXT'
    ]);

$database->addRowToTable($genericTable, 'tx_extension_table', ['title' => 'Test', 'pid' => 1]);
```

### Custom Table Class

```php
use Typo3DevSpringboard\Feature\Database\Table;

class ExtensionTable extends Table
{
    public static function getIdentifier(): string
    {
        return 'extension_table';
    }

    protected function getTableSchemas(): array
    {
        return [
            'tx_extension_table' => [
                'uid' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'pid' => 'INTEGER DEFAULT 0',
                'title' => 'TEXT'
            ]
        ];
    }
}

$database->getTable(ExtensionTable::class)
    ->addRow(['title' => 'Test', 'pid' => 1], 'tx_extension_table');
```

## Custom Features

### Implement Feature

```php
use Typo3DevSpringboard\Feature\Typo3FeatureInterface;

class CustomFeature implements Typo3FeatureInterface
{
    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }

    public static function getIdentifier(): string
    {
        return 'custom';
    }

    public function requiredFeatureIdentifier(): array
    {
        return [];  // Dependencies
    }

    public function execute(array $features): static
    {
        // Your logic here
        return $this;
    }
}

$builder->addFeature(CustomFeature::make(Typo3Version::TYPO3_13_LTS));
```

### Override Existing Feature

```php
class CustomDatabase extends \Typo3DevSpringboard\Feature\Database
{
    // Override methods
}

$builder->addFeature(CustomDatabase::make(Typo3Version::TYPO3_13_LTS));
```

### Anonymous Class - New Feature

```php
$customFeature = new class(Typo3Version::TYPO3_13_LTS) implements Typo3FeatureInterface {
    public function __construct(
        private readonly Typo3Version $version
    ) {}
    
    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }
    
    public static function getIdentifier(): string
    {
        return 'anonymous_feature';
    }
    
    public function requiredFeatureIdentifier(): array
    {
        return ['Database'];
    }
    
    public function execute(array $features): static
    {
        $database = $features['Database'];
        // Custom logic
        return $this;
    }
};

$builder->addFeature($customFeature);
```

### Anonymous Class - Override Feature

```php
// Override existing Database feature with anonymous class
$customDatabase = new class(Typo3Version::TYPO3_13_LTS) extends \Typo3DevSpringboard\Feature\Database {
    public static function getIdentifier(): string
    {
        return 'Database';  // Same identifier to override
    }
    
    public function execute(array $features): static
    {
        // Custom database setup
        parent::execute($features);
        // Additional logic
        return $this;
    }
};

$builder->addFeature($customDatabase);
```

### Anonymous Class - Custom Table

```php
$database = $builder->getFeature(Database::class);

$customTable = new class(Typo3Version::TYPO3_13_LTS) extends Table {
    public function __construct(
        private readonly Typo3Version $version
    ) {}
    
    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }
    
    public static function getIdentifier(): string
    {
        return 'anonymous_table';
    }
    
    protected function getTableSchemas(): array
    {
        return [
            'tx_anonymous_table' => [
                'uid' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'data' => 'TEXT'
            ]
        ];
    }
};

$database->addTable($customTable);
$customTable->addRow(['data' => 'test'], 'tx_anonymous_table');
```

## Feature Access

```php
// Get feature
$database = $builder->getFeature(\Typo3DevSpringboard\Feature\Database::class);
$fileSystem = $builder->getFeature(\Typo3DevSpringboard\Feature\FileSystem::class);
$request = $builder->getFeature(\Typo3DevSpringboard\Feature\Request::class);
$site = $builder->getFeature(\Typo3DevSpringboard\Feature\Site::class);

// Remove feature
$builder->removeFeature(\Typo3DevSpringboard\Feature\Site::class);
```

## Core Features

- **Request**: HTTP request simulation
- **FileSystem**: Directory and config file management
- **Site**: Site configuration (config.yaml)
- **Database**: SQLite database and tables

## Built-in Tables

- **Pages**: pages table
- **TtContent**: tt_content table
- **Template**: sys_template table
- **Caches**: All cache tables
- **GenericTable**: Custom table handler

## Testing Example

```php
class ExtensionTest extends \PHPUnit\Framework\TestCase
{
    public function testOutput(): void
    {
        $output = Builder::make(Typo3Version::TYPO3_13_LTS)
            ->installDir(__DIR__ . '/../.Build')
            ->withRequest('/test')
            ->build()
            ->execute(true);
        
        $this->assertStringContainsString('expected', $output);
    }
}
```

## CLI Usage

```php
$uri = $_SERVER['argv'][1] ?? '/';

Builder::make(Typo3Version::TYPO3_13_LTS)
    ->installDir(__DIR__ . '/.Build')
    ->withRequest($uri)
    ->build()
    ->execute();
```

## Complex Example

```php
// Custom feature with dependencies
class MyFeature implements Typo3FeatureInterface
{
    public function requiredFeatureIdentifier(): array
    {
        return ['Database', 'FileSystem'];
    }
    
    public function execute(array $features): static
    {
        $database = $features['Database'];
        $fileSystem = $features['FileSystem'];
        // Use dependencies
        return $this;
    }
}

// Custom table with default values
$genericTable = $database->getTable(GenericTable::class)
    ->addTableSchema('tx_ext_table', [...])
    ->withDefaultDataCallable(function($data, $table) {
        $data['tstamp'] ??= time();
        $data['crdate'] ??= time();
        return $data;
    });

// Multiple languages with specific IDs
$builder
    ->setSiteLanguage(SiteLanguage::EN, 0)
    ->setSiteLanguage(SiteLanguage::DE, 1)
    ->addSiteLanguage(/* custom languages */);

// Custom site configuration
$builder->setSiteConfig([
    'rootPageId' => 1,
    'base' => 'https://example.com/',
    'languages' => [/* custom */]
]);
```

## License

MIT
