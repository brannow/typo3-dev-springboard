<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use Exception;
use Typo3DevSpringboard\Typo3Version;

class Site implements Typo3FeatureInterface
{
    /**
     * @param array<string, array<int|null|SiteLanguage>> $languages
     * @param int $pageId
     * @param array|null $siteConfig
     * @param bool $disableFallbackLanguage
     */
    private function __construct(
        private array $languages = [],
        private int $pageId = 1,
        private ?array $siteConfig = [],
        private bool $disableFallbackLanguage = false
    )
    {}

    public static function make(): self
    {
        return new self();
    }

    public function disableFallbackLanguage(bool $disable): self
    {
        $this->disableFallbackLanguage = $disable;

        return $this;
    }

    public function addLanguage(SiteLanguage ... $siteLanguage): self
    {
        foreach ($siteLanguage as $language) {
            $this->setLanguage($language, null);
        }

        return $this;
    }

    public function setPageId(int $pageId): self
    {
        $this->pageId = $pageId;

        return $this;
    }

    public function setSiteConfig(?array $siteConfig): self
    {
        $this->siteConfig = $siteConfig;

        return $this;
    }

    public function setLanguage(SiteLanguage $siteLanguage, ?int $languageId): self
    {
        $this->languages[$siteLanguage->name] = ['l' => $siteLanguage, 'id' => $languageId];
        return $this;
    }


    public function execute(Typo3Version $version, array $features): Typo3FeatureInterface
    {
        // custom set, finished
        if ($this->siteConfig !== null) {
            return $this;
        }

        // fallback default
        if (empty($this->languages) && $this->disableFallbackLanguage) {
            $this->addLanguage(SiteLanguage::EN);
        }

        /** @var Request $request */
        $request = $features[Request::class] ?? throw new Exception('required feature not given: ' . Request::class);

        $siteConfig = [
            'rootPageId' => $this->pageId,
            'base' => $request->getBaseUrl(),
        ];

        $languagesById = [];
        $requestIdSpotLanguage = [];
        foreach ($this->languages as $languageConfig) {
            $languagesById[] = $language =  $languageConfig['l'];
            $requestLangId = $languageConfig['id'] ?? null;
            if ($requestLangId > 0) {
                $requestIdSpotLanguage[$requestLangId] = $language;
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

    public function requiredFeatures(): array
    {
        return [Request::class];
    }
}
