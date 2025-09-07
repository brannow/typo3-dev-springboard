<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

use Closure;

class GenericTable extends Table
{
    protected array $schemaList = [];
    /**
     * @var null|Closure(array, string): array
     */
    protected ?Closure $defaultDataCallable = null;

    protected function setupDefaultData(array $data, string $table): array
    {
        /** @var null|Closure(array, string): array $closure */
        $closure = $this->defaultDataCallable;
        if (is_callable($closure)) {
            return $closure($data, $table);
        }

        return $data;
    }

    /**
     * @param Closure(array, string): array $defaultDataCallable
     * @return $this
     */
    public function withDefaultDataCallable(Closure $defaultDataCallable): static
    {
        $this->defaultDataCallable = $defaultDataCallable;

        return $this;
    }


    public function addTableSchema(string $table, array $schema): static
    {
        $this->schemaList[$table] = $schema;

        return $this;
    }

    public static function getIdentifier(): string
    {
        return 'generic_table';
    }

    protected function getTableSchemas(): array
    {
        return $this->schemaList;
    }
}
