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
    private string $additionalCode = '';
    private array $settings = [
        'SYS' => [
            'encryptionKey' => 'not-secure-secret',
            'trustedHostsPattern' => '.*'
        ]
    ];

    public function requiredFeatureIdentifier(): array
    {
        return [Site::getIdentifier()];
    }

    public static function getIdentifier(): string
    {
        return 'FileSystem';
    }

    private function __construct(
        private readonly Typo3Version $version,
        private string $baseDir = './'
    )
    {}

    public function getScriptPath(): string
    {
        return Path::join($this->baseDir, $this->publicDir, 'index.php');
    }

    public function setBaseDir(string $baseDir): static
    {
        $this->baseDir = $baseDir;

        return $this;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    public static function make(Typo3Version $version): static
    {
        return new static($version);
    }

    public function setConfigDir(string $configDir): static
    {
        $this->configDir = $configDir;
        return $this;
    }

    public function setVarDir(string $varDir): static
    {
        $this->varDir = $varDir;
        return $this;
    }

    /**
     * @param string $additionalCode
     * @return void
     */
    public function setAdditionalCode(string $additionalCode): void
    {
        $this->additionalCode = $additionalCode;
    }

    public function setPublicDir(string $publicDir): static
    {
        $this->publicDir = $publicDir;
        return $this;
    }

    public function addSettings(array $settings): static
    {
        $this->settings = array_replace_recursive($this->settings, $settings);
        return $this;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = $siteName;
        return $this;
    }

    /**
     * @param array $features
     * @return $this
     * @throws Exception
     */
    public function execute( array $features): static
    {
        /** @var Site $site */
        $site = $features[Site::getIdentifier()] ?? throw new Exception('Site Feature not provided');
        $baseDir = realpath($this->baseDir);

        if (!file_exists($baseDir)) {
            throw new Exception('Base Typo3 Directory not found: ' . $baseDir);
        }
        // base structure
        putenv('TYPO3_PATH_APP='. $baseDir);
        $this->createDirIfNotExists($this->varDir, $baseDir);
        $this->createDirIfNotExists($this->publicDir, $baseDir);
        $configDir = $this->createDirIfNotExists($this->configDir, $baseDir);
        // system config
        $systemDir = $this->createDirIfNotExists('system', $configDir);
        $this->createFileIfNotExists('additional.php', $systemDir, '<?php' . PHP_EOL . $this->additionalCode);
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
        if (!file_exists($trueDir)) mkdir($trueDir, 0755, true);

        return $trueDir;
    }

    private function createFileIfNotExists(string $file, string $baseDir, string $content = ''): string
    {
        $trueFile = Path::join($baseDir, $file);
        file_put_contents($trueFile, $content . PHP_EOL);
        return $trueFile;
    }
}
