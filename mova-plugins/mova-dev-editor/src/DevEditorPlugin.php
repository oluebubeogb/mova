<?php
/**
 * Main orchestrator for Mova Dev Editor
 */

declare(strict_types=1);

namespace MovaDevEditor;

use Mova\Plugin\PluginManager;
use MovaDevEditor\Editor\EditorRenderer;
use MovaDevEditor\Storage\MetaStorage;
use MovaDevEditor\Validation\ValidatorPipeline;
use MovaDevEditor\Render\FrontendInjector;
use MovaDevEditor\Security\RoleGate;

class DevEditorPlugin
{
    private array $config;
    private string $basePath;
    private MetaStorage $storage;
    private ValidatorPipeline $validator;
    private FrontendInjector $injector;
    private RoleGate $gate;
    private EditorRenderer $editor;

    public function __construct(array $config, string $basePath)
    {
        $this->config = $config;
        $this->basePath = $basePath;
        $this->storage = new MetaStorage();
        $this->validator = new ValidatorPipeline($config);
        $this->injector = new FrontendInjector($config, $this->storage);
        $this->gate = new RoleGate($config);
        $this->editor = new EditorRenderer($config, $basePath, $this->storage, $this->gate);
    }

    public function register(): void
    {
        PluginManager::addAction('content.edit.render', function (array $context = []) {
            $this->editor->render($context);
        });

        PluginManager::addFilter('content.save.before', function (array $payload) {
            return $this->onSaveBefore($payload);
        });

        PluginManager::addFilter('content.render.body', function (string $body, array $content = []) {
            return $this->injector->filterBody($body, $content);
        });

        PluginManager::addAction('theme.render.head', function (array $context = []) {
            $this->injector->renderHead($context);
        });
        PluginManager::addAction('theme.render.footer', function (array $context = []) {
            $this->injector->renderFooter($context);
        });
    }

    private function onSaveBefore(array $payload): array
    {
        $req = $payload['request'] ?? null;
        if (!$req) {
            return $payload;
        }

        $mode = (string) $req->post('editor_mode', 'visual');
        if ($mode !== 'dev') {
            if (isset($payload['data']['meta']) && is_array($payload['data']['meta'])) {
                $payload['data']['meta']['editor_mode'] = 'visual';
            }
            return $payload;
        }

        if (!$this->gate->canUseDevMode()) {
            $payload['errors'][] = 'You do not have permission to use Dev Mode.';
            return $payload;
        }

        $html = (string) $req->post('body', '');
        $css  = (string) $req->post('raw_css', '');
        $js   = (string) $req->post('raw_js', '');

        $result = $this->validator->validate($html, $css, $js);

        if (!empty($result['errors'])) {
            $status = (string) ($payload['data']['status'] ?? 'draft');
            $block = !empty($this->config['block_publish_on_errors']);
            if ($block && $status === 'published') {
                $payload['errors'] = array_merge($payload['errors'] ?? [], $result['errors']);
                $payload['data']['status'] = 'draft';
            } else {
                $payload['warnings'] = array_merge($payload['warnings'] ?? [], $result['errors']);
            }
        }

        if (!isset($payload['data']['meta']) || !is_array($payload['data']['meta'])) {
            $payload['data']['meta'] = [];
        }
        $payload['data']['meta']['editor_mode'] = 'dev';
        $payload['data']['meta']['raw_css'] = $css;
        $payload['data']['meta']['raw_js'] = $js;
        $payload['data']['meta']['use_site_chrome'] = $req->post('use_site_chrome') ? '1' : '0';
        $payload['data']['meta']['dev_validation'] = json_encode([
            'errors'   => $result['errors'],
            'warnings' => $result['warnings'] ?? [],
            'at'       => date('c'),
        ]);

        $payload['data']['body'] = $html;

        return $payload;
    }
}
