<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use Exception;
use Typo3DevSpringboard\Typo3Version;

class Site implements Typo3FeatureInterface
{
    /**
     * @param Typo3Version $version
     * @param array<string, array<int|null|SiteLanguage>> $languages
     * @param int $pageId
     * @param array|null $siteConfig
     * @param bool $disableFallbackLanguage
     */
    private function __construct(
        private readonly Typo3Version $version,
        private array $languages = [],
        private int $pageId = 1,
        private ?array $siteConfig = null,
        private bool $disableFallbackLanguage = false
    )
    {}

    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }

    public function requiredFeatureIdentifier(): array
    {
        return [Request::getIdentifier()];
    }

    public static function getIdentifier(): string
    {
        return 'Site';
    }

    public function disableFallbackLanguage(bool $disable): static
    {
        $this->disableFallbackLanguage = $disable;

        return $this;
    }

    public function addLanguage(SiteLanguage ... $siteLanguage): static
    {
        foreach ($siteLanguage as $language) {
            $this->setLanguage($language, null);
        }

        return $this;
    }

    public function setPageId(int $pageId): static
    {
        $this->pageId = $pageId;

        return $this;
    }

    public function setSiteConfig(?array $siteConfig): static
    {
        $this->siteConfig = $siteConfig;

        return $this;
    }

    public function setLanguage(SiteLanguage $siteLanguage, ?int $languageId): static
    {
        $this->languages[$siteLanguage->name] = ['l' => $siteLanguage, 'id' => $languageId];
        return $this;
    }

    /**
     * @param array $features
     * @return static
     * @throws Exception
     */
    public function execute(array $features): static
    {
        // custom set, finished
        if ($this->siteConfig !== null) {
            return $this;
        }

        // fallback default
        if (empty($this->languages) && !$this->disableFallbackLanguage) {
            $this->addLanguage(SiteLanguage::DE);
            $this->setLanguage(SiteLanguage::EN, 0);
        }

        /** @var Request $request */
        $request = $features[Request::getIdentifier()] ?? throw new Exception('Request Feature not provided');

        $siteConfig = [
            'rootPageId' => $this->pageId,
            'base' => $request->getBaseUrl(),
        ];

        $languagesById = [];
        $requestIdSpotLanguage = [];
        foreach ($this->languages as $languageConfig) {
            $language =  $languageConfig['l'];
            $requestLangId = $languageConfig['id'] ?? null;
            if ($requestLangId !== null) {
                $requestIdSpotLanguage[$requestLangId] = $language;
            } else {
                $languagesById[] = $language;
            }
        }

        foreach ($requestIdSpotLanguage as $spot => $language) {
            array_splice( $languagesById, $spot, 0, [$language]);
        }
        $languageConfig = [];
        foreach ($languagesById as $id => $language) {
            $languageConfig[$id] = $language->getSiteConfig($id);
        }
        if (!empty($languageConfig)) {
            $siteConfig['languages'] = $languageConfig;
        }

        $this->siteConfig = $siteConfig;

        return $this;
    }

    public function getSiteConfig(): array
    {
        return $this->siteConfig;
    }
}
