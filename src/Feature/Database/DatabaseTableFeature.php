<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

use Typo3DevSpringboard\Feature\Database;
use Typo3DevSpringboard\Typo3Version;

interface DatabaseTableFeature
{
    public static function make(Typo3Version $version): static;

    public function execute(Database $database): static;

    public static function getIdentifier(): string;

    public function addRow(array $data, string $table): static;

    public function getSchemaForTable(string $dbTableName): array;
}
