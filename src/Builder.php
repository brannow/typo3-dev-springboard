<?php declare(strict_types=1);

namespace Typo3DevSpringboard;

use Exception;
use Typo3DevSpringboard\Feature\{FileSystem, Request, Site, Database, Typo3FeatureInterface};

final class Builder
{
    /**
     * @var array<class-string<Typo3FeatureInterface>, Typo3FeatureInterface>
     */
    private array $singletons = [];

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
        if (!is_a($featureClass, Typo3FeatureInterface::class, true)) {
            throw new Exception("Feature {$featureClass} must implement Typo3FeatureInterface");
        }

        return $this->singletons[$featureClass] ??= $featureClass::make();
    }

    /**
     * Add a configured feature - this overrides any default
     */
    public function addFeature(Typo3FeatureInterface $feature): self
    {
        $this->singletons[$feature::class] = $feature;
        return $this;
    }

    /**
     * Topological sort with circular dependency detection
     */
    private function buildExecutionOrder(): array
    {
        $graph  = [];
        $inDegree = [];
        foreach ($this->singletons as $class => $instance) {
            $graph[$class]  = $instance->requiredFeatures();
            $inDegree[$class] = 0;
        }

        // fill in-degree
        foreach ($graph as $class => $deps) {
            foreach ($deps as $d) {
                if (!isset($inDegree[$d])) {        // auto-add missing
                    $feature      = $this->getFeature($d);   // creates + stores + returns
                    $inDegree[$d] = 0;
                    $graph[$d]    = $feature->requiredFeatures();
                }
                $inDegree[$d]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $class => $deg) {
            if ($deg === 0) $queue[] = $class;
        }

        $order = [];
        while ($queue) {
            $curr = array_shift($queue);
            $order[] = $curr;

            foreach ($graph[$curr] as $dep) {
                if (--$inDegree[$dep] === 0) {
                    $queue[] = $dep;
                }
            }
        }

        if (count($order) !== count($inDegree)) {
            throw new Exception('Circular dependency detected between features');
        }

        return array_reverse($order);
    }

    /**
     * @param bool $return
     * @return string|null
     */
    public function execute(bool $return = false): ?string
    {
        // Ensure all mandatory features are included as singletons
        foreach ($this->getMandatoryFeatures() as $mandatoryClass) {
            $this->getFeature($mandatoryClass);
        }

        // execute in topological order
        /** @var array<class-string, Typo3FeatureInterface> $executed */
        $executed = [];
        $order = $this->buildExecutionOrder();
        foreach ($order as $class) {
            $feature           = $this->getFeature($class);
            $requiredClasses   = $feature->requiredFeatures();   // list<class-string>
            // pick only the dependencies that have already been executed
            $requestedFeatures = array_intersect_key($executed, array_flip($requiredClasses));

            // execute and remember
            $feature->execute($this->version, $requestedFeatures);
            $executed[$class]  = $feature;
        }

        /** @var FileSystem $fs */
        $fs = $this->getFeature(FileSystem::class);
        $script = $fs->getScriptPath();
        if (!file_exists($script)) {
            throw new Exception("TYPO3 entry point not found: {$script}");
        }

        if ($return) {
            ob_start();
            require $script;
            return ob_get_clean();
        }
        require $script;
        return null;
    }
}
