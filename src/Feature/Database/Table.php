<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

use Exception;
use Typo3DevSpringboard\Feature\Database;
use Typo3DevSpringboard\Typo3Version;
use PDO;

abstract class Table implements DatabaseTableFeature
{
    abstract protected function getTableSchemas(Typo3Version $version): array;

    public function requiredFeatures(): array
    {
        return [Database::class];
    }

    public function execute(Typo3Version $version, Database $database): self
    {
        $pdo = $database->getPdo();
        foreach ($this->getTableSchemas($version) as $table => $metadata) {
            $this->createTable($version, $pdo, $table, $metadata);
        }

        return $this;
    }

    protected function createTable(Typo3Version $version, PDO $dbConnection, string $tableName, array $schemaData): void
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
