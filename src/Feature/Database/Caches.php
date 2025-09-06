<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

use Typo3DevSpringboard\Typo3Version;

class Caches extends Table
{
    protected function getTableSchemas(Typo3Version $version): array
    {
        return [
            'cache_hash' => [
                'id' => 'INTEGER PRIMARY KEY',
                'identifier' => 'TEXT NOT NULL',
                'expires' => 'INTEGER DEFAULT 0',
                'content' => 'TEXT'
            ],
            'cache_pages' => [
                'id' => 'INTEGER PRIMARY KEY',
                'identifier' => 'TEXT NOT NULL',
                'expires' => 'INTEGER DEFAULT 0',
                'content' => 'TEXT'
            ],
            'cache_rootline' => [
                'id' => 'INTEGER PRIMARY KEY',
                'identifier' => 'TEXT NOT NULL',
                'expires' => 'INTEGER DEFAULT 0',
                'content' => 'TEXT'
            ],
            'cache_hash_tags' => [
                'id' => 'INTEGER PRIMARY KEY',
                'identifier' => 'TEXT NOT NULL',
                'tag' => 'TEXT NOT NULL',
            ],
            'cache_pages_tags' => [
                'id' => 'INTEGER PRIMARY KEY',
                'identifier' => 'TEXT NOT NULL',
                'tag' => 'TEXT NOT NULL',
            ],
            'cache_rootline_tags' => [
                'id' => 'INTEGER PRIMARY KEY',
                'identifier' => 'TEXT NOT NULL',
                'tag' => 'TEXT NOT NULL',
            ],
            'sys_refindex' => [
                'hash' => 'TEXT PRIMARY KEY',
                'tablename' => 'TEXT',
                'recuid' => 'INTEGER',
                'field' => 'TEXT',
                'flexpointer' => 'TEXT',
                'softref_key' => 'TEXT',
                'softref_id' => 'TEXT',
                'sorting' => 'INTEGER DEFAULT 0',
                'workspace' => 'INTEGER DEFAULT 0',
                'ref_table' => 'TEXT',
                'ref_uid' => 'INTEGER',
                'ref_string' => 'TEXT'
            ],
        ];
    }
}
