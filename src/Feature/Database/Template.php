<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

class Template extends Table
{
    public static function getIdentifier(): string
    {
        return 'sys_template';
    }

    protected function setupDefaultData(array $data, string $table): array
    {
        $data['tstamp'] ??= time();
        $data['crdate'] ??= time();
        return $data;
    }

    public function addTypoScriptTemplate(string $template, int $pageId, ?int $root = null, ?string $templateName = null): static
    {
        $this->addRow([
            'pid' => $pageId,
            'root' => $root ?? $pageId,
            'config' => addslashes($template),
            'title' => $templateName ?? ('template_'.md5((string)microtime()))
        ], 'sys_template');

        return $this;
    }

    protected function getTableSchemas(): array
    {
        return [
            'sys_template' => [
                'uid' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'pid' => 'INTEGER DEFAULT 0',
                'title' => 'TEXT',
                'sitetitle' => 'TEXT',
                'hidden' => 'INTEGER DEFAULT 0',
                'deleted' => 'INTEGER DEFAULT 0',
                'starttime' => 'INTEGER DEFAULT 0',
                'endtime' => 'INTEGER DEFAULT 0',
                'sorting' => 'INTEGER DEFAULT 0',
                'crdate' => 'INTEGER DEFAULT 0',
                'cruser_id' => 'INTEGER DEFAULT 0',
                'tstamp' => 'INTEGER DEFAULT 0',
                'root' => 'INTEGER DEFAULT 0',
                'clear' => 'INTEGER DEFAULT 0',
                'config' => 'TEXT',
                'constants' => 'TEXT',
                'nextLevel' => 'TEXT',
                'basedOn' => 'TEXT',
                'includeStaticAfterBasedOn' => 'INTEGER DEFAULT 0',
            ],
        ];
    }
}
