<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

enum SiteLanguage
{
    case DE;
    case EN;

    /**
     * Whole language record as used by TYPO3 or any custom router.
     */
    public function getSiteConfig(int $languageId = 0): array
    {
        return match ($this) {
            self::EN => [
                'title'          => 'English',
                'enabled'        => true,
                'languageId'     => $languageId,
                'base'           => '/',
                'typo3Language'  => 'default',
                'locale'         => 'en_US.UTF-8',
                'iso-639-1'      => 'en',
                'navigationTitle'=> 'English',
                'hreflang'       => 'en-us',
                'direction'      => 'ltr',
                'flag'           => 'us',
            ],
            self::DE => [
                'title'          => 'Deutsch',
                'enabled'        => true,
                'languageId'     => $languageId,
                'base'           => '/de/',
                'typo3Language'  => 'de',
                'locale'         => 'de_DE.UTF-8',
                'iso-639-1'      => 'de',
                'navigationTitle'=> 'Deutsch',
                'hreflang'       => 'de-de',
                'direction'      => 'ltr',
                'flag'           => 'de',
            ],
        };
    }

    /* ---------- convenience accessors ---------- */

    public function languageId(): int
    {
        return $this->getSiteConfig()['languageId'];
    }

    public function locale(): string
    {
        return $this->getSiteConfig()['locale'];
    }

    public function base(): string
    {
        return $this->getSiteConfig()['base'];
    }
}
