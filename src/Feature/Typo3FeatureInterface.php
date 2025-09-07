<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use Typo3DevSpringboard\Typo3Version;

interface Typo3FeatureInterface
{
    public static function make(Typo3Version $version): static;

    /**
     * @param array<class-string<Typo3FeatureInterface>, Typo3FeatureInterface> $features
     * @return static
     */
    public function execute(array $features): static;

    /**
     * @return string[]
     */
    public function requiredFeatureIdentifier(): array;

    public static function getIdentifier(): string;
}
