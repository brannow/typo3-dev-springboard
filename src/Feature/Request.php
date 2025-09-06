<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use Symfony\Component\Filesystem\Path;
use Typo3DevSpringboard\Typo3Version;

class Request implements Typo3FeatureInterface
{
    public function __construct(
        private string $uri = '/',
        private string $domain = 'localhost',
        private bool $https = false,
        private string $method = 'GET'
    )
    {}

    public function requiredFeatures(): array
    {
        return [];
    }

    public static function make(): self
    {
        return new self();
    }

    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function setHttps(bool $https): self
    {
        $this->https = $https;
        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param Typo3Version $version
     * @param array<class-string<Typo3FeatureInterface>, Typo3FeatureInterface> $features
     * @return self
     */
    public function execute(Typo3Version $version, array $features): self
    {
        $_SERVER['HTTP_HOST'] = $this->domain;
        $_SERVER['REQUEST_URI'] = $this->uri;
        $_SERVER['REQUEST_METHOD'] = strtoupper($this->method);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SERVER_NAME'] = $this->domain;
        if ($this->https) {
            $_SERVER['HTTPS'] = 'on';
        }

        return $this;
    }

    public function getBaseUrl(): string
    {
        return ($this->https?'https':'http').'://'.trim($this->domain, '/').'/';
    }

    public function getFullUrl(): string
    {
        return Path::join($this->getBaseUrl(), $this->uri);
    }
}
