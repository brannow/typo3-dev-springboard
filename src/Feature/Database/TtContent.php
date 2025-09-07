<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

class TtContent extends Table
{
    public static function getIdentifier(): string
    {
        return 'tt_content';
    }

    protected function getTableSchemas(): array
    {
        return [
            'tt_content' => [
                'uid' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'pid' => 'INTEGER DEFAULT 0',
                'header' => 'TEXT DEFAULT ""',
                'bodytext' => 'TEXT DEFAULT ""',
                'CType' => 'TEXT DEFAULT ""',
                'colPos' => 'INTEGER DEFAULT 0',
                'hidden' => 'INTEGER DEFAULT 0',
                'deleted' => 'INTEGER DEFAULT 0',
                'starttime' => 'INTEGER DEFAULT 0',
                'endtime' => 'INTEGER DEFAULT 0',
                'fe_group' => 'TEXT DEFAULT ""',
                'tstamp' => 'INTEGER DEFAULT 0',
                'crdate' => 'INTEGER DEFAULT 0',
                'cruser_id' => 'INTEGER DEFAULT 0',
                'sorting' => 'INTEGER DEFAULT 0',
                'sys_language_uid' => 'INTEGER DEFAULT 0',
                'l10n_parent' => 'INTEGER DEFAULT 0',
                'l18n_cfg' => 'INTEGER DEFAULT 0',
                't3ver_wsid' => 'INTEGER DEFAULT 0',
                't3ver_id' => 'INTEGER DEFAULT 0',
                't3ver_oid' => 'INTEGER DEFAULT 0',
                't3ver_stage' => 'INTEGER DEFAULT 0',
                't3ver_state' => 'INTEGER DEFAULT 0'
            ]
        ];
    }
}
