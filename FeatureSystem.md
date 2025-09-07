## Feature System Deep Dive

### How Features Work

Features are identified by their `getIdentifier()` method, not their class name. This is the key to the override system.

The same System is applied to the DatabaseTableFeature

```php
// The Builder stores features by identifier, not class
$builder->singletons['Database'] = $databaseFeature;
$builder->singletons['Request'] = $requestFeature;
```

### Feature Lifecycle

1. **Registration**: Features are registered by identifier
2. **Dependency Resolution**: Builder topologically sorts based on `requiredFeatureIdentifier()`
3. **Execution**: Features execute in dependency order, receiving required features

```php
// When you call getFeature(), it:
// 1. Checks if identifier exists in singletons
// 2. If not, creates instance via ::make()
// 3. Stores by identifier for reuse

$db1 = $builder->getFeature(Database::class);      // Creates and stores as 'Database'
$db2 = $builder->getFeature('Database');           // Returns same instance
$db3 = $builder->getFeature(Database::class);      // Returns same instance
```

### Override Mechanism

Since features are stored by identifier, not class, you can replace any feature:

```php
// Original Database feature is registered as 'Database'
class MyDatabase extends Database {
    public static function getIdentifier(): string {
        return 'Database';  // SAME identifier = override
    }
}

// This REPLACES the original Database feature
$builder->addFeature(MyDatabase::make($version));

// All code requesting 'Database' now gets MyDatabase
$db = $builder->getFeature('Database');  // Returns MyDatabase instance
```

### Anonymous Override Pattern

```php
// Quick override for testing - modify just one method
$customDb = new class($version) extends Database {
    public static function getIdentifier(): string {
        return 'Database';  // Override by using same identifier
    }
    
    public function getDbSettingsConfig(string $baseDir = ''): array {
        $config = parent::getDbSettingsConfig($baseDir);
        $config['DB']['Connections']['Default']['driver'] = 'pdo_mysql';
        return $config;
    }
};

$builder->addFeature($customDb);
```

### Table Feature System

Tables work identically - identified by `getIdentifier()`:

```php
// Tables are stored in Database feature by identifier
$database->tables['pages'] = $pagesTable;
$database->tables['generic_table'] = $genericTable;

// Override existing table
$customPages = new class($version) extends Pages {
    public static function getIdentifier(): string {
        return 'pages';  // Same as Pages::getIdentifier()
    }
    
    protected function getTableSchemas(): array {
        $schema = parent::getTableSchemas();
        $schema['pages']['custom_field'] = 'TEXT';
        return $schema;
    }
};

$database->addTable($customPages);  // Replaces default Pages table
```

### Dependency Injection

Features receive their dependencies through `execute()`:

```php
class MyFeature implements Typo3FeatureInterface {
    public function requiredFeatureIdentifier(): array {
        return ['Database', 'FileSystem'];  // By identifier, not class
    }
    
    public function execute(array $features): static {
        // $features is associative: identifier => instance
        $db = $features['Database'];      // Could be Database or MyDatabase
        $fs = $features['FileSystem'];    // Could be any FileSystem implementation
        
        // Your feature doesn't care about the concrete class
        // Just that it has the expected methods
        return $this;
    }
}
```

### Why This Design?

- **Testability**: Replace any component without changing consuming code
- **Flexibility**: Override just what you need, keep the rest
- **Isolation**: Each feature is independent, dependencies explicit
- **Simplicity**: No DI container, just identifier-based storage

### Practical Override Examples

```php
// Scenario 1: Use MySQL instead of SQLite
$builder->addFeature(new class($version) extends Database {
    public static function getIdentifier(): string { return 'Database'; }
    
    public function execute(array $features): static {
        // Custom MySQL connection logic
        $this->pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
        // Continue with table creation...
        return $this;
    }
});

// Scenario 2: Add custom cache headers to Request
$builder->addFeature(new class($version) extends Request {
    public static function getIdentifier(): string { return 'Request'; }
    
    public function execute(array $features): static {
        parent::execute($features);
        $_SERVER['HTTP_CACHE_CONTROL'] = 'no-cache';
        return $this;
    }
});

// Scenario 3: Modify FileSystem paths on the fly
$customFS = $builder->getFeature('FileSystem');
$builder->addFeature(new class($version, $customFS) extends FileSystem {
    public function __construct($version, private FileSystem $original) {
        parent::__construct($version, $this->original->getBaseDir());
    }
    
    public static function getIdentifier(): string { return 'FileSystem'; }
    
    public function execute(array $features): static {
        // Change var directory for tests
        $this->setVarDir('var_test');
        return parent::execute($features);
    }
});
```

### Feature Registry Map

The Builder maintains a registry map to track class names:

```php
// Internal tracking
$builder->featureRegistryMap = [
    'Database' => Database::class,        // Original mapping
    'FileSystem' => FileSystem::class,
    'Request' => Request::class,
    'Site' => Site::class
];

// After override
$builder->addFeature($customDatabase);
// Now: 'Database' => CustomDatabase::class

// getFeature() uses identifier first, then checks registry
$db = $builder->getFeature('Database');            // By identifier
$db = $builder->getFeature(Database::class);       // By class (finds via identifier)
$db = $builder->getFeature(CustomDatabase::class); // Would create new if not registered
```
