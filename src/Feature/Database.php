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
    private string $databaseFileName = 'database.sqlite';
    /**
     * @var array<class-string<DatabaseTableFeature>, DatabaseTableFeature>
     */
    private array $tables = [];
    private array $tablesRegistryMap = [];
    private ?PDO $pdo = null;

    private function __construct(
        private readonly Typo3Version $version
    )
    {
        // init default required default tables
        // we use the classes on purpose to also fill up the TableRegistryMap
        $this->getTable(Pages::class);
        $this->getTable(Caches::class);
        $this->getTable(TtContent::class);
        $this->getTable(Template::class);
    }

    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }

    public function setDriver(string $driver): void
    {
        $this->driver = $driver;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function setDatabaseFileName(string $databaseFileName): void
    {
        $this->databaseFileName = $databaseFileName;
    }

    public function getDbSettingsConfig(string $baseDir = ''): array
    {
        return [
            'DB' => [
                'Connections' => [
                    'Default' => [
                        'driver' => 'pdo_'. $this->driver,
                        'path' => $this->getDatabasePath($baseDir)
                    ]
                ]
            ]
        ];
    }

    /**
     * get/ create new features and add them to the stack
     * @param class-string<DatabaseTableFeature>|string $tableClassOrIdentifier
     * @throws Exception
     */
    public function getTable(string $tableClassOrIdentifier): DatabaseTableFeature
    {
        if (is_a($tableClassOrIdentifier, DatabaseTableFeature::class, true)) {
            $identifier = $tableClassOrIdentifier::getIdentifier();
            $this->tablesRegistryMap[$identifier] ??= $tableClassOrIdentifier;

            return $this->tables[$identifier] ??= $tableClassOrIdentifier::make($this->version);

        } elseif(isset($this->tablesRegistryMap[$tableClassOrIdentifier])) {

            $featureClass = $this->tablesRegistryMap[$tableClassOrIdentifier];
            if (is_a($featureClass, DatabaseTableFeature::class, true)) {

                return $this->tables[$featureClass::getIdentifier()] ??= $featureClass::make($this->version);
            }
        }

        throw new Exception('Table '. $tableClassOrIdentifier
            . ' not found. Table must implement '. DatabaseTableFeature::class .'. '
            . 'the Identifier of the Custom Table was never Registered via addFeature. also Possible, getTable once via Class-String instead of Identifier.' );
    }

    /**
     *  overwrite features, use with caution! data-loss can happen, there is no merge, all feature are singleton!
     */
    public function addTable(DatabaseTableFeature $table): static
    {
        $this->tables[$table::getIdentifier()] = $table;
        $this->tablesRegistryMap[$table::getIdentifier()] = $table::class;
        return $this;
    }

    /**
     * removes / drops a table completely.
     * @param string|DatabaseTableFeature|class-string<DatabaseTableFeature> $identifierOrObjectOrClassName
     * @return static
     */
    public function removeTable(string|DatabaseTableFeature $identifierOrObjectOrClassName): static
    {
        if ($identifierOrObjectOrClassName instanceof DatabaseTableFeature || (is_a($identifierOrObjectOrClassName, DatabaseTableFeature::class, true))) {
            $identifier = $identifierOrObjectOrClassName::getIdentifier();
        } else {
            $identifier = $identifierOrObjectOrClassName;
        }

        if (isset($this->tables[$identifier])) {
            unset($this->tables[$identifier]);
        }

        return $this;
    }

    /**
     * @param string|class-string<DatabaseTableFeature>|DatabaseTableFeature $identifierOrObjectOrClassName
     * @param string $dbTableName
     * @param array $row
     * @return $this
     * @throws Exception
     */
    public function addRowToTable(string|DatabaseTableFeature $identifierOrObjectOrClassName, string $dbTableName, array $row): static
    {
        if ($identifierOrObjectOrClassName instanceof DatabaseTableFeature) {
            $table = $identifierOrObjectOrClassName;
            // complete new table add to tables list
            if (!isset($this->tables[$table::getIdentifier()]) || $this->tables[$table::getIdentifier()] !== $table) {
                $this->addTable($table);
            }
        } else {
            $table = $this->getTable($identifierOrObjectOrClassName);
        }

        $table->addRow($row, $dbTableName);
        return $this;
    }

    public function requiredFeatureIdentifier(): array
    {
        return [FileSystem::getIdentifier()];
    }

    public static function getIdentifier(): string
    {
        return 'Database';
    }

    /**
     * @param array $features
     * @return $this
     * @throws Exception
     */
    public function execute(array $features): static
    {
        /** @var FileSystem $fileSystem */
        $fileSystem = $features[FileSystem::getIdentifier()] ?? throw new Exception('FileSystem Feature not provided');

        // Determine database path
        $this->path = $this->getDatabasePath($fileSystem->getBaseDir());
        // Create database connection
        $dsn = 'sqlite:' . $this->path ?? throw new Exception('cannot create Database no path nor other configs provided');

        // Always recreate for fresh state
        if ($this->path && file_exists($this->path)) {
            unlink($this->path);
        }

        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach ($this->tables as $table) {
            $table->execute($this);
        }

        return $this;
    }

    public function getDatabasePath(string $baseDir): ?string
    {
        if ($this->path !== null)
            return $this->path;

        if (empty($this->databaseFileName))
            return null;

        return Path::join($baseDir, 'var', $this->databaseFileName);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }
}
