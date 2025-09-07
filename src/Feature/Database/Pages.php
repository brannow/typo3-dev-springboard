<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

class Pages extends Table
{
    public static function getIdentifier(): string
    {
        return 'pages';
    }

    protected function setupDefaultData(array $data, string $table): array
    {
        $data['tstamp'] ??= time();
        $data['crdate'] ??= time();
        return $data;
    }

    protected function getTableSchemas(): array
    {
        return [
            'pages' => [
                'uid' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'pid' => 'INTEGER DEFAULT 0',
                'title' => 'TEXT',
                'doktype' => 'INTEGER DEFAULT 1',
                'hidden' => 'INTEGER DEFAULT 0',
                'deleted' => 'INTEGER DEFAULT 0',
                'starttime' => 'INTEGER DEFAULT 0',
                'endtime' => 'INTEGER DEFAULT 0',
                'fe_group' => 'TEXT DEFAULT ""',
                'tstamp' => 'INTEGER DEFAULT 0',
                'crdate' => 'INTEGER DEFAULT 0',
                'cruser_id' => 'INTEGER DEFAULT 0',
                'sorting' => 'INTEGER DEFAULT 0',
                'slug' => 'TEXT DEFAULT "/"',
                'SYS_LASTCHANGED' => 'INTEGER DEFAULT 0',
                'sys_language_uid' => 'INTEGER DEFAULT 0',
                'l10n_parent' => 'INTEGER DEFAULT 0',
                'l18n_cfg' => 'INTEGER DEFAULT 0',
                'mount_pid' => 'INTEGER DEFAULT 0',
                'mount_pid_ol' => 'INTEGER DEFAULT 0',
                't3ver_wsid' => 'INTEGER DEFAULT 0',
                't3ver_id' => 'INTEGER DEFAULT 0',
                't3ver_oid' => 'INTEGER DEFAULT 0',
                't3ver_stage' => 'INTEGER DEFAULT 0',
                't3ver_state' => 'INTEGER DEFAULT 0'
            ]
        ];
    }
}
