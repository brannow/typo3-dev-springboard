<?php declare(strict_types=1);

namespace Typo3DevSpringboard;

use Exception;
use Typo3DevSpringboard\Feature\{FileSystem, Request, Site, Database, Typo3FeatureInterface};

final class Builder
{
    /**
     * @var array<class-string<Typo3FeatureInterface>, Typo3FeatureInterface>
     */
    private array $featureStack = [];

    /**
     * @var array<class-string<Typo3FeatureInterface>>
     */
    private array $processing = [];

    /**
     * @var array<class-string<Typo3FeatureInterface>, Typo3FeatureInterface>
     */
    private array $processed = [];

    /**
     * Core features required for any TYPO3 to boot - version specific
     * @return array<class-string<Typo3FeatureInterface>>
     */
    private function getMandatoryFeatures(): array
    {
        // Base features for all versions
        $features = [
            Request::class,
            FileSystem::class,
            Site::class,
            Database::class,
        ];

        // Add version-specific mandatory features
        switch ($this->version) {
            case Typo3Version::TYPO3_13_LTS:
                // V13 might require additional core features
                break;

            case Typo3Version::TYPO3_12_LTS:
                // V12 specific requirements
                break;
        }

        return $features;
    }

    public static function make(Typo3Version $version): self
    {
        return new self($version);
    }

    private function __construct(
        private readonly Typo3Version $version
    ) {
        // Don't initialize anything here - let execute() handle it
    }

    public function installDir(string $baseDir): self
    {
        /** @var FileSystem $fileSystem */
        $fileSystem = $this->getFeature(FileSystem::class);
        $fileSystem->setBaseDir($baseDir);
        return $this;
    }

    public function withUri(string $uri): self
    {
        /** @var Request $request */
        $request = $this->getFeature(Request::class);
        $request->setUri($uri);
        return $this;
    }

    public function withDomain(string $domain, bool $https = false): self
    {
        /** @var Request $request */
        $request = $this->getFeature(Request::class);
        $request->setDomain($domain)->setHttps($https);
        return $this;
    }

    /**
     * @param class-string<Typo3FeatureInterface> $featureClass
     */
    public function getFeature(string $featureClass): Typo3FeatureInterface
    {
        return $this->featureStack[$featureClass] ??= new $featureClass();
    }

    /**
     * Add a configured feature - this overrides any default
     */
    public function addFeature(Typo3FeatureInterface $feature): self
    {
        $this->featureStack[$feature::class] = $feature;
        return $this;
    }

    /**
     * Topological sort with circular dependency detection
     */
    private function resolveFeatures(): array
    {
        $this->processed = [];
        $this->processing = [];

        // Ensure all mandatory features exist (use user-provided or create defaults)
        foreach ($this->getMandatoryFeatures() as $mandatoryClass) {
            if (!isset($this->featureStack[$mandatoryClass])) {
                $this->featureStack[$mandatoryClass] = new $mandatoryClass();
            }
        }

        foreach ($this->featureStack as $featureClass => $feature) {
            if (!isset($this->processed[$featureClass])) {
                $this->processFeature($featureClass, $feature);
            }
        }

        return $this->processed;
    }

    private function processFeature(string $featureClass, Typo3FeatureInterface $feature): void
    {
        // Circular dependency detection
        if (isset($this->processing[$featureClass])) {
            throw new Exception(sprintf(
                'Circular dependency detected: %s',
                implode(' -> ', array_keys($this->processing)) . ' -> ' . $featureClass
            ));
        }

        $this->processing[$featureClass] = true;

        // Process dependencies first
        foreach ($feature->requiredFeatures() as $requiredClass) {
            if (!isset($this->processed[$requiredClass])) {
                // Auto-create if it's mandatory, otherwise error
                if (!isset($this->featureStack[$requiredClass])) {
                    if (in_array($requiredClass, $this->getMandatoryFeatures())) {
                        $this->featureStack[$requiredClass] = new $requiredClass();
                    } else {
                        throw new Exception(sprintf(
                            'Feature %s requires %s, but it was not added',
                            $featureClass,
                            $requiredClass
                        ));
                    }
                }
                $this->processFeature($requiredClass, $this->featureStack[$requiredClass]);
            }
        }

        // Mark as processed
        unset($this->processing[$featureClass]);
        $this->processed[$featureClass] = $feature;
    }

    public function execute(bool $return = false): ?string
    {
        // Resolve features - this ensures mandatory features exist
        $orderedFeatures = $this->resolveFeatures();

        // Execute features in correct order
        foreach ($orderedFeatures as $feature) {
            $feature->execute($this->version, $this->processed);
        }

        /** @var FileSystem $fileSystem */
        $fileSystem = $this->processed[FileSystem::class];
        $scriptPath = $fileSystem->getScriptPath();

        if (!file_exists($scriptPath)) {
            throw new Exception('TYPO3 entry point not found: ' . $scriptPath);
        }

        if ($return) {
            ob_start();
            require $scriptPath;
            return ob_get_clean();
        }

        require $scriptPath;
        return null;
    }
}
