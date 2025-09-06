<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature\Database;

use Typo3DevSpringboard\Feature\Database;
use Typo3DevSpringboard\Typo3Version;

interface DatabaseTableFeature
{
    public function execute(Typo3Version $version, Database $database): self;
}
