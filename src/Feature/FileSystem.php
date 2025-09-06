<?php declare(strict_types=1);

namespace Typo3DevSpringboard\Feature;

use Exception;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Typo3DevSpringboard\Typo3Version;

class FileSystem implements Typo3FeatureInterface
{
    private string $varDir = 'var';
    private string $configDir = 'config';
    private string $publicDir = 'public';
    private string $siteName = 'main';
    private array $settings = [
        'SYS' => [
            'encryptionKey' => 'not-secure-secret',
            'trustedHostsPattern' => '.*'
        ]
    ];

    private function __construct(
        private string $baseDir = './'
    )
    {}

    public function getScriptPath(): string
    {
        return Path::join($this->baseDir, $this->publicDir, 'index.php');
    }

    public function setBaseDir(string $baseDir): self
    {
        $this->baseDir = $baseDir;

        return $this;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    public static function make(): self
    {
        return new self();
    }

    public function requiredFeatures(): array
    {
        return [
            Site::class
        ];
    }

    public function setConfigDir(string $configDir): self
    {
        $this->configDir = $configDir;
        return $this;
    }

    public function setVarDir(string $varDir): self
    {
        $this->varDir = $varDir;
        return $this;
    }

    public function setPublicDir(string $publicDir): self
    {
        $this->publicDir = $publicDir;
        return $this;
    }

    public function addSettings(array $settings): self
    {
        $this->settings = array_replace_recursive($this->settings, $settings);
        return $this;
    }

    public function setSiteName(string $siteName): self
    {
        $this->siteName = $siteName;
        return $this;
    }

    public function execute(Typo3Version $version, array $features): self
    {
        /** @var Site $site */
        $site = $features[Site::class] ?? throw new Exception('Site Feature not provided');

        if (!file_exists($this->baseDir)) {
            throw new Exception('Base Typo3 Directory not found: ' . $this->baseDir);
        }
        // base structure
        putenv('TYPO3_PATH_APP='.$this->baseDir);
        $this->createDirIfNotExists($this->varDir, $this->baseDir);
        $this->createDirIfNotExists($this->publicDir, $this->baseDir);
        $configDir = $this->createDirIfNotExists($this->configDir, $this->baseDir);
        // system config
        $systemDir = $this->createDirIfNotExists('system', $configDir);
        $this->createFileIfNotExists('additional.php', $systemDir, '<?php');
        $this->createFileIfNotExists('settings.php', $systemDir, '<?php'. PHP_EOL.'return '.var_export($this->settings, true).';');
        // site config
        $sitesDir = $this->createDirIfNotExists('sites', $configDir);
        $defaultSiteDir = $this->createDirIfNotExists($this->siteName, $sitesDir);
        $this->createFileIfNotExists('config.yaml', $defaultSiteDir, Yaml::dump($site->getSiteConfig(), 4, 2));

        return $this;
    }

    private function createDirIfNotExists(string $dir, string $basePath = ''): string
    {
        $trueDir = Path::join($basePath, $dir);
        if (!file_exists($trueDir)) mkdir($trueDir, 0755);

        return $trueDir;
    }

    private function createFileIfNotExists(string $file, string $baseDir, string $content = ''): string
    {
        $trueFile = Path::join($baseDir, $file);
        file_put_contents($trueFile, $content . PHP_EOL);
        return $trueFile;
    }
}
