<?php declare(strict_types=1);

namespace Typo3DevSpringboard;

use Exception;
use Typo3DevSpringboard\Feature\{FileSystem, Request, Site, Database, SiteLanguage, Typo3FeatureInterface};
use Throwable;

class Builder
{
    /**
     * Identifier => Typo3FeatureInterface
     * @var array<string, Typo3FeatureInterface>
     */
    private array $singletons = [];
    /**
     * Identifier => Typo3FeatureInterface-Class-Name
     * @var array<string, class-string<Typo3FeatureInterface>>
     */
    private array $featureRegistryMap = [];

    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }

    private function __construct(
        private readonly Typo3Version $version
    ) {
        // init default required default features
        // we use the classes on purpose to also fill up the FeatureRegistryMap
        $this->getFeature(FileSystem::class);
        $this->getFeature(Database::class);
        $this->getFeature(Request::class);
        $this->getFeature(Site::class);
    }

    /**
     * currently a must-have, maybe there is an auto-detect way, dunno
     * @param string $baseDir
     * @return $this
     * @throws Exception
     */
    public function installDir(string $baseDir): static
    {
        /** @var FileSystem $fileSystem */
        $feature = $this->getFeature(FileSystem::getIdentifier());
        $feature->setBaseDir($baseDir);
        return $this;
    }

    /**
     * REQUEST FEATURES
     */

    /**
     * @param string $uri
     * @param string|null $domain
     * @param string|null $method
     * @param bool $https
     * @return $this
     * @throws Exception
     */
    public function withRequest(string $uri, ?string $domain = null, ?string $method = null, bool $https = false): static
    {
        /** @var Request $feature */
        $feature = $this->getFeature(Request::getIdentifier());
        $feature->setUri($uri);
        if ($domain)
            $feature->setDomain($domain);
        if ($method)
            $feature->setMethod($method);
        $feature->setHttps($https);
        return $this;
    }


    /**
     * SITE FEATURES
     */

    /**
     * @param SiteLanguage ...$language
     * @return $this
     * @throws Exception
     */
    public function addSiteLanguage(SiteLanguage ...$language): static
    {
        /** @var Site $feature */
        $feature = $this->getFeature(Site::getIdentifier());
        $feature->addLanguage(...$language);
        return $this;
    }

    /**
     * @param SiteLanguage $language
     * @param int|null $languageId
     * @return $this
     * @throws Exception
     */
    public function setSiteLanguage(SiteLanguage $language, ?int $languageId = null): static
    {
        /** @var Site $feature */
        $feature = $this->getFeature(Site::getIdentifier());
        $feature->setLanguage($language, $languageId);
        return $this;
    }

    /**
     * @param int $pageId
     * @return $this
     * @throws Exception
     */
    public function setSiteRootPageId(int $pageId): static
    {
        /** @var Site $feature */
        $feature = $this->getFeature(Site::getIdentifier());
        $feature->setPageId($pageId);
        return $this;
    }

    /**
     * this enables / disables the Site Autogeneration, and use a given FULL site config (on null enable site Config generation)
     *
     * @param array|null $siteConfig
     * @return $this
     * @throws Exception
     */
    public function setSiteConfig(?array $siteConfig): static
    {
        /** @var Site $feature */
        $feature = $this->getFeature(Site::getIdentifier());
        $feature->setSiteConfig($siteConfig);

        return $this;
    }

    /**
     * DATABASE FEATURES
     */

    public function addDatabasePageRecord(array $row): static
    {
        /** @var Database $feature */
        $feature = $this->getFeature(Database::getIdentifier());
        $feature->addRowToTable(Database\Pages::getIdentifier(),'pages', $row);
        return $this;
    }

    public function addDatabaseContentRecord(array $row): static
    {
        /** @var Database $feature */
        $feature = $this->getFeature(Database::getIdentifier());
        $feature->addRowToTable(Database\TtContent::getIdentifier(),'tt_content', $row);

        return $this;
    }

    public function addDatabaseTypoScriptTemplate(string $typoScript, int $pageId, ?int $root = null, ?string $templateName = null): static
    {
        /** @var Database $feature */
        $feature = $this->getFeature(Database::getIdentifier());
        /** @var Database\Template $templateTable */
        $templateTable = $feature->getTable(Database\Template::getIdentifier());
        $templateTable->addTypoScriptTemplate($typoScript, $pageId, $root, $templateName);

        return $this;
    }

    /**
     * INTERNAL ++++++++++++++++++++
     */

    /**
     * get/ create new features and add them to the stack
     * @param class-string<Typo3FeatureInterface>|string $featureClassOrIdentifier
     * @throws Exception
     */
    public function getFeature(string $featureClassOrIdentifier): Typo3FeatureInterface
    {
        if (is_a($featureClassOrIdentifier, Typo3FeatureInterface::class, true)) {
            $identifier = $featureClassOrIdentifier::getIdentifier();
            $this->featureRegistryMap[$identifier] ??= $featureClassOrIdentifier;

            return $this->singletons[$identifier] ??= $featureClassOrIdentifier::make($this->version);

        } elseif(isset($this->featureRegistryMap[$featureClassOrIdentifier])) {

            $featureClass = $this->featureRegistryMap[$featureClassOrIdentifier];
            if (is_a($featureClass, Typo3FeatureInterface::class, true)) {

                return $this->singletons[$featureClass::getIdentifier()] ??= $featureClass::make($this->version);
            }
        }

        throw new Exception('Feature '. $featureClassOrIdentifier
            . ' not found. Feature must implement '. Typo3FeatureInterface::class .'. '
            . 'the Identifier of the Custom Feature was never Registered via addFeature. also Possible, getFeature once via Class-String instead of Identifier.' );
    }

    /**
     *  overwrite features, use with caution! data-loss can happen, there is no merge, all feature are singleton!
     */
    public function addFeature(Typo3FeatureInterface $feature): static
    {
        $this->singletons[$feature::getIdentifier()] = $feature;
        $this->featureRegistryMap[$feature::getIdentifier()] = $feature::class;
        return $this;
    }

    /**
     * removes / drops a feature completely.
     * @param string|Typo3FeatureInterface|class-string<Typo3FeatureInterface> $identifierOrObjectOrClassName
     * @return self
     */
    public function removeFeature(string|Typo3FeatureInterface $identifierOrObjectOrClassName): static
    {
        if ($identifierOrObjectOrClassName instanceof Typo3FeatureInterface || (is_a($identifierOrObjectOrClassName, Typo3FeatureInterface::class, true))) {
            $identifier = $identifierOrObjectOrClassName::getIdentifier();
        } else {
            $identifier = $identifierOrObjectOrClassName;
        }

        if (isset($this->singletons[$identifier])) {
            unset($this->singletons[$identifier]);
        }

        return $this;
    }

    /**
     * Topological sort with circular dependency detection
     */
    private function buildExecutionOrder(): array
    {
        $graph  = [];
        $inDegree = [];
        foreach ($this->singletons as $identifier => $instance) {
            $graph[$identifier] = $instance->requiredFeatureIdentifier();
            $inDegree[$identifier] = 0;
        }

        // fill in-degree
        foreach ($graph as $deps) {
            foreach ($deps as $d) {
                if (!isset($inDegree[$d])) {        // auto-add missing
                    $feature      = $this->getFeature($d);   // creates + stores + returns
                    $inDegree[$d] = 0;
                    $graph[$d]    = $feature->requiredFeatureIdentifier();
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
     * @return $this
     * @throws Exception
     */
    public function build(): static
    {
        $database = null;
        $fileSystem = null;
        try {
            // special case DB and filesystem
            /** @var Database $database */
            $database = $this->getFeature(Database::getIdentifier());
            /** @var FileSystem $fileSystem */
            $fileSystem = $this->getFeature(FileSystem::getIdentifier());
        } catch (Throwable) {}

        if($database && $fileSystem) {
            $dbSettings = $database->getDbSettingsConfig($fileSystem->getBaseDir());
            $fileSystem->addSettings($dbSettings);
        }

        // execute in topological order
        /** @var array<class-string, Typo3FeatureInterface> $executed */
        $executed = [];
        foreach ($this->buildExecutionOrder() as $identifier) {
            $feature = $this->getFeature($identifier);
            $requiredIdentifier = $feature->requiredFeatureIdentifier();
            // pick only the dependencies that have already been executed
            $requestedFeatures = array_intersect_key($executed, array_flip($requiredIdentifier));

            if (count($requestedFeatures) !== count($requiredIdentifier)) {
                throw new Exception('Feature \''. $feature::class .'\' required: ' . implode(', ', $requiredIdentifier) . 'but only these Features found: ' . implode(', ', array_keys($requestedFeatures)));
            }

            // execute and remember
            $feature->execute($requestedFeatures);
            $executed[$identifier]  = $feature;
        }

        return $this;
    }

    /**
     * @param bool $return
     * @return string|null
     * @throws Exception
     */
    public function execute(bool $return = false): ?string
    {
        /** @var FileSystem $fs */
        $fs = $this->getFeature(FileSystem::getIdentifier());
        $script = $fs->getScriptPath();
        if (!file_exists($script)) {
            throw new Exception('TYPO3 entry point not found: '. $script);
        }

        if ($return) {
            ob_start();
            require_once $script;
            return ob_get_clean();
        }
        require_once $script;
        return null;
    }
}
