<?php
declare(strict_types=1);

namespace Mova\Update;

use Mova\Core\Bootstrap;

/**
 * Reads/writes mova.json and public mova.txt identity files.
 */
class IdentityService
{
    public function root(): string
    {
        return Bootstrap::path('root') ?: dirname(__DIR__, 2);
    }

    public function jsonPath(): string
    {
        return $this->root() . '/mova.json';
    }

    public function txtPath(): string
    {
        return $this->root() . '/mova.txt';
    }

    public function markerPath(): string
    {
        return $this->root() . '/.mova';
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        $path = $this->jsonPath();
        if (!is_file($path)) {
            return $this->defaults();
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? array_replace_recursive($this->defaults(), $data) : $this->defaults();
    }

    public function version(): string
    {
        $fromJson = (string) ($this->read()['version'] ?? '');
        if ($fromJson !== '') {
            return $fromJson;
        }
        return (string) Bootstrap::config('app_version', '0.0.0');
    }

    /**
     * Refresh mova.json site block + public mova.txt (+ ensure .mova marker).
     *
     * @param array{name?: string, version?: string, touch_updated?: bool} $opts
     */
    public function refresh(array $opts = []): void
    {
        $data = $this->read();
        if (isset($opts['name']) && $opts['name'] !== '') {
            $data['site']['name'] = $opts['name'];
        }
        if (isset($opts['version']) && $opts['version'] !== '') {
            $data['version'] = $opts['version'];
        } else {
            $data['version'] = (string) Bootstrap::config('app_version', $data['version'] ?? '1.0.0');
        }
        if (!isset($data['site']['created_at']) || $data['site']['created_at'] === '') {
            $data['site']['created_at'] = gmdate('c');
        }
        if ($opts['touch_updated'] ?? true) {
            $data['site']['updated_at'] = gmdate('c');
        }
        $data['paths'] = [
            'uploads' => 'mova-uploads',
            'plugins' => 'mova-plugins',
            'themes'  => 'mova-themes',
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($this->jsonPath(), $json);

        $created = substr((string) ($data['site']['created_at'] ?? ''), 0, 10) ?: date('Y-m-d');
        $updated = substr((string) ($data['site']['updated_at'] ?? ''), 0, 10) ?: date('Y-m-d');
        $siteName = (string) ($data['site']['name'] ?? Bootstrap::config('app_name', 'Mova'));
        $version = (string) ($data['version'] ?? '1.0.0');

        $txt = "This site runs on Mova CMS.\n"
            . "Site: {$siteName}\n"
            . "Created: {$created}\n"
            . "Last modified: {$updated}\n"
            . "Version: {$version}\n";
        file_put_contents($this->txtPath(), $txt);

        if (!is_file($this->markerPath())) {
            file_put_contents($this->markerPath(), "Mova CMS\n");
        }
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'name' => 'mova',
            'title' => 'Mova CMS',
            'tagline' => 'Content that moves.',
            'version' => (string) Bootstrap::config('app_version', '1.0.0'),
            'php' => '>=8.1',
            'channel' => 'stable',
            'homepage' => '',
            'docs' => '',
            'support' => '',
            'release_manifest' => '',
            'site' => [
                'name' => (string) Bootstrap::config('app_name', 'Mova'),
                'created_at' => '',
                'updated_at' => '',
            ],
            'paths' => [
                'uploads' => 'mova-uploads',
                'plugins' => 'mova-plugins',
                'themes' => 'mova-themes',
            ],
        ];
    }
}
