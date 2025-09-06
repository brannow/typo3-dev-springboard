<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use Typo3DevSpringboard\Typo3Version;

interface Typo3FeatureInterface
{
    /**
     * @param Typo3Version $version
     * @param array<class-string<Typo3FeatureInterface>, Typo3FeatureInterface> $features
     * @return self
     */
    public function execute(Typo3Version $version, array $features): self;

    /**
     * @return class-string<Typo3FeatureInterface>[]
     */
    public function requiredFeatures(): array;
}
