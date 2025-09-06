<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use PDO;
use Symfony\Component\Filesystem\Path;
use Typo3DevSpringboard\Feature\Database\Caches;
use Typo3DevSpringboard\Feature\Database\DatabaseTableFeature;
use Typo3DevSpringboard\Feature\Database\Pages;
use Typo3DevSpringboard\Feature\Database\Template;
use Typo3DevSpringboard\Feature\Database\TtContent;
use Typo3DevSpringboard\Typo3Version;
use Exception;

class Database implements Typo3FeatureInterface
{
    private string $driver = 'sqlite';
    private ?string $path = null;
    /**
     * @var array<class-string<DatabaseTableFeature>, DatabaseTableFeature>
     */
    private array $tables = [];
    private ?PDO $pdo = null;

    public static function make(): self
    {
        return new self();
    }

    public function getDbSettingsConfig(Typo3Version $version, string $baseDir = ''): array
    {
        if ($this->path === null && $this->driver === 'sqlite') {
            $this->path = Path::join($baseDir, 'var', 'database.sqlite');
        }

        return [
            'DB' => [
                'Connections' => [
                    'Default' => [
                        'driver' => 'pdo_'. $this->driver,
                        'path' => $this->path
                    ]
                ]
            ]
        ];
    }

    public function addTable(DatabaseTableFeature $databaseTable): self
    {
        $this->tables[$databaseTable::class] = $databaseTable;
        return $this;
    }

    /**
     * @param class-string<DatabaseTableFeature> $tableClass
     * @return DatabaseTableFeature
     * @throws Exception
     */
    public function getTable(string $tableClass): DatabaseTableFeature
    {
        if (!is_a($tableClass, DatabaseTableFeature::class, true)) {
            throw new Exception("Feature ". $tableClass ." must implement Typo3FeatureInterface");
        }

        return $this->tables[$tableClass] ??= new $tableClass();
    }

    public function requiredFeatures(): array
    {
        return [FileSystem::class];
    }

    public function execute(Typo3Version $version, array $features): self
    {
        // setup default pages
        $this->getTable(Pages::class);
        $this->getTable(Caches::class);
        $this->getTable(TtContent::class);
        $this->getTable(Template::class);

        /** @var FileSystem $fileSystem */
        $fileSystem = $features[FileSystem::class] ?? throw new Exception('FileSystem Feature not provided');

        // Determine database path
        if ($this->path === null && $this->driver === 'sqlite') {
            $this->path = Path::join($fileSystem->getBaseDir(), 'var', 'database.sqlite');
        }

        // Create database connection
        $dsn = 'sqlite:' . $this->path;

        // Always recreate for fresh state
        if ($this->path && file_exists($this->path)) {
            unlink($this->path);
        }

        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach ($this->tables as $table) {
            $table->execute($version, $this);
        }

        return $this;
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }
}
