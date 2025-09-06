<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use PDO;
use Symfony\Component\Filesystem\Path;
use Typo3DevSpringboard\Typo3Version;
use Exception;

class Database implements Typo3FeatureInterface
{
    private string $driver = 'sqlite';
    private ?string $path = null;
    private array $tables = [];
    private array $records = [];
    private ?PDO $pdo = null;

    public static function make(): self
    {
        return new self();
    }

    public function memory(): self
    {
        $this->driver = 'sqlite:memory';
        $this->path = null;
        return $this;
    }

    public function addTable(string $name, string $schema): self
    {
        $this->tables[$name] = $schema;
        return $this;
    }

    public function addRecord(string $table, array $data): self
    {
        $this->records[$table][] = $data;
        return $this;
    }

    public function requiredFeatures(): array
    {
        return [FileSystem::class];
    }

    public function execute(Typo3Version $version, array $features): self
    {
        /** @var FileSystem $fileSystem */
        $fileSystem = $features[FileSystem::class] ?? throw new Exception('FileSystem Feature not provided');

        // Determine database path
        if ($this->path === null && $this->driver === 'sqlite') {
            $this->path = Path::join($fileSystem->getBaseDir(), 'var', 'database.sqlite');
        }

        // Create database connection
        $dsn = $this->driver === 'sqlite:memory'
            ? 'sqlite::memory:'
            : 'sqlite:' . $this->path;

        // Always recreate for fresh state
        if ($this->path && file_exists($this->path)) {
            unlink($this->path);
        }

        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create minimal required tables
        $this->createMinimalTables($version);

        // Create custom tables
        foreach ($this->tables as $name => $schema) {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS $name ($schema)");
        }

        // Insert records
        $this->insertMinimalRecords();

        foreach ($this->records as $table => $rows) {
            foreach ($rows as $row) {
                $this->insertRecord($table, $row);
            }
        }

        // Update FileSystem settings with database config
        $settings = [
            'SYS' => [
                'encryptionKey' => 'dev-key-' . bin2hex(random_bytes(16)),
                'trustedHostsPattern' => '.*'
            ],
            'DB' => [
                'Connections' => [
                    'Default' => [
                        'driver' => 'pdo_sqlite',
                        'path' => $this->path ?? ':memory:'
                    ]
                ]
            ]
        ];

        $fileSystem->setSettings($settings);

        return $this;
    }

    private function createMinimalTables(Typo3Version $version): void
    {
        // Pages table - required for any TYPO3
        $this->pdo->exec('CREATE TABLE pages (
            uid INTEGER PRIMARY KEY,
            pid INTEGER DEFAULT 0,
            title TEXT,
            slug TEXT DEFAULT "/",
            doktype INTEGER DEFAULT 1,
            deleted INTEGER DEFAULT 0,
            hidden INTEGER DEFAULT 0,
            sys_language_uid INTEGER DEFAULT 0,
            tstamp INTEGER DEFAULT 0,
            crdate INTEGER DEFAULT 0
        )');

        // Content table
        $this->pdo->exec('CREATE TABLE tt_content (
            uid INTEGER PRIMARY KEY,
            pid INTEGER DEFAULT 0,
            header TEXT,
            bodytext TEXT,
            CType TEXT DEFAULT "text",
            colPos INTEGER DEFAULT 0,
            deleted INTEGER DEFAULT 0,
            hidden INTEGER DEFAULT 0,
            sys_language_uid INTEGER DEFAULT 0,
            tstamp INTEGER DEFAULT 0,
            crdate INTEGER DEFAULT 0
        )');

        // Cache tables
        foreach (['cache_hash', 'cache_pages', 'cache_rootline'] as $cache) {
            $this->pdo->exec("CREATE TABLE $cache (
                id INTEGER PRIMARY KEY,
                identifier TEXT NOT NULL,
                expires INTEGER DEFAULT 0,
                content TEXT
            )");

            $this->pdo->exec("CREATE TABLE {$cache}_tags (
                id INTEGER PRIMARY KEY,
                identifier TEXT NOT NULL,
                tag TEXT NOT NULL
            )");
        }

        // Version specific tables
        if ($version === Typo3Version::TYPO3_13_LTS) {
            // Add v13 specific tables/columns
        }
    }

    private function insertMinimalRecords(): void
    {
        $now = time();

        // Insert root page if no pages exist
        $count = $this->pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
        if ($count == 0) {
            $this->insertRecord('pages', [
                'uid' => 1,
                'pid' => 0,
                'title' => 'Root',
                'slug' => '/',
                'tstamp' => $now,
                'crdate' => $now
            ]);
        }
    }

    private function insertRecord(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }
}
