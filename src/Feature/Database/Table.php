<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

use Exception;
use Typo3DevSpringboard\Feature\Database;
use Typo3DevSpringboard\Typo3Version;
use PDO;

abstract class Table implements DatabaseTableFeature
{
    private array $rows = [];

    private function __construct(
        protected readonly Typo3Version $version
    )
    {}

    public function addRow(array $data, string $table): static
    {
        $schema = $this->getSchemaForTable($table);
        foreach ($data as $field => $value) {
            if (!isset($schema[$field])) {
                throw new Exception('column \''. $field .'\' in table \''. $table .'\' not found, only available (' . implode(', ', array_keys($schema)) . ')');
            }
        }

        if (count($data) === 0) {
            throw new Exception('Row for table \''. $table .'\' is Empty');
        }

        $this->rows[$table][] = $data;

        return $this;
    }

    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }

    public function getSchemaForTable(string $dbTableName): array
    {
        $tables = $this->getTableSchemas();
        return $this->getTableSchemas()[$dbTableName] ?? throw new Exception('table \''. $dbTableName .'\' not found, only available (' . implode(', ', array_keys($tables)) . ')');
    }

    abstract protected function getTableSchemas(): array;

    public function execute(Database $database): static
    {
        $pdo = $database->getPdo();
        foreach ($this->getTableSchemas() as $table => $metadata) {
            $this->createTable($pdo, $table, $metadata);
        }

        foreach ($this->rows as $table => $rows) {
            foreach ($rows as $row) {
                $this->insertRow($pdo, $table, $row);
            }
        }

        return $this;
    }

    protected function insertRow(PDO $dbConnection, string $tableName, array $row): void
    {
        $row = $this->setupDefaultData($row, $tableName);
        $row = $this->escapeData($dbConnection, $row);
        $dbConnection->exec('INSERT INTO ' . $tableName . '('. implode(', ', array_keys($row)) .') VALUES ('. implode(', ', $row) .')');
    }

    protected function escapeData(PDO $dbConnection, array $data): array
    {
        foreach ($data as $field => $item) {
            $type = match (true) {
                $item === null => PDO::PARAM_NULL,
                is_bool($item) => PDO::PARAM_BOOL,
                is_int($item) => PDO::PARAM_INT,
                default => PDO::PARAM_STR
            };

            if ($type === PDO::PARAM_STR)
                $data[$field] = $dbConnection->quote((string)$item, $type);
        }

        return $data;
    }

    protected function setupDefaultData(array $data, string $table): array
    {
        return $data;
    }

    protected function createTable(PDO $dbConnection, string $tableName, array $schemaData): void
    {
        $schema = [];
        foreach ($schemaData as $field => $meta) {
            $schema[] = $field . ' ' . $meta;
        }

        $schemaText = implode(','.PHP_EOL, $schema);
        if ($dbConnection->exec('CREATE TABLE IF NOT EXISTS '. $tableName .' ('. $schemaText .')') === false) {
            throw new Exception('failed to create Table \'' . $tableName . '\' with schema: (' . $schemaText.')');
        }
    }
}
