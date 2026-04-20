<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TemplateRegistryGenerator
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @return array<string,mixed> */
    public function buildPayload(): array
    {
        $templatesTable = $this->resolveTableName((string) $this->cfg('templates_table', 'site_templates'));
        $tvLinkTable = $this->resolveTableName((string) $this->cfg('template_tv_pivot_table', 'site_tmplvar_templates'));
        $tvsTable = $this->resolveTableName((string) $this->cfg('tvs_table', 'site_tmplvars'));

        if (!$templatesTable || !$tvLinkTable || !$tvsTable) {
            throw new RuntimeException('Required tables not found (site_templates/site_tmplvar_templates/site_tmplvars).');
        }

        $templateColumns = $this->safeColumnListing($templatesTable);
        $tvColumns = $this->safeColumnListing($tvsTable);
        $tvLinkColumns = $this->safeColumnListing($tvLinkTable);
        $viewColumn = $this->firstExistingColumn($templateColumns, (array) $this->cfg('template_view_columns', []));

        $templates = DB::table($templatesTable)->orderBy('id')->get();

        $rows = [];
        $tvCatalog = [];
        $stats = [
            'templates_total' => 0,
            'missing_controllers' => 0,
            'missing_views' => 0,
            'placeholder_views' => 0,
            'total_tvs_links' => 0,
            'unique_tvs' => 0,
        ];

        foreach ($templates as $template) {
            $id = (int) ($template->id ?? 0);
            $alias = (string) ($template->templatealias ?? '');
            $name = (string) ($template->templatename ?? '');

            $controller = $this->resolveControllerMeta($template, $alias);
            $controllerPathAbsolute = $this->resolveControllerPath($controller['class']);
            $controllerExists = $controllerPathAbsolute !== null && is_file($controllerPathAbsolute);
            $controllerPath = $controllerPathAbsolute !== null
                ? $this->toProjectRelativePath($controllerPathAbsolute)
                : null;

            $explicitView = $viewColumn ? (string) ($template->{$viewColumn} ?? '') : '';
            $view = $this->resolveViewMeta($explicitView, $alias);
            [$viewPathAbsolute, $viewName] = $this->resolveViewPath($view);
            $viewExists = is_file($viewPathAbsolute);
            $viewPath = $this->toProjectRelativePath($viewPathAbsolute);
            $isPlaceholder = $viewExists ? $this->isPlaceholderView($viewPathAbsolute) : false;

            $tvRows = $this->loadTemplateTvs($tvLinkTable, $tvsTable, $tvColumns, $tvLinkColumns, $id);
            $tvRefs = [];
            foreach ($tvRows as $tv) {
                $tvId = (int) ($tv['id'] ?? 0);
                if ($tvId <= 0) {
                    continue;
                }

                if (!isset($tvCatalog[$tvId])) {
                    $catalogItem = $tv;
                    unset($catalogItem['rank']);
                    $tvCatalog[$tvId] = $catalogItem;
                }

                $tvRef = ['id' => $tvId];
                if (array_key_exists('rank', $tv) && $tv['rank'] !== null) {
                    $tvRef['rank'] = (int) $tv['rank'];
                }
                $tvRefs[] = $tvRef;
            }

            $flags = [
                'missing_controller' => !$controllerExists,
                'missing_view' => !$viewExists,
                'placeholder_view' => $isPlaceholder,
            ];

            $stats['templates_total']++;
            $stats['total_tvs_links'] += count($tvRefs);
            $stats['missing_controllers'] += $flags['missing_controller'] ? 1 : 0;
            $stats['missing_views'] += $flags['missing_view'] ? 1 : 0;
            $stats['placeholder_views'] += $flags['placeholder_view'] ? 1 : 0;

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'alias' => $alias,
                'controller' => [
                    'class' => $controller['class'],
                    'path' => $controllerPath,
                    'exists' => $controllerExists,
                    'source' => $controller['source'],
                ],
                'view' => [
                    'name' => $viewName,
                    'path' => $viewPath,
                    'exists' => $viewExists,
                    'placeholder' => $isPlaceholder,
                    'source' => $view['source'],
                ],
                'tv_refs' => $tvRefs,
                'tv_count' => count($tvRefs),
                'flags' => $flags,
            ];
        }

        ksort($tvCatalog);
        $tvCatalog = array_values($tvCatalog);
        $stats['unique_tvs'] = count($tvCatalog);

        $clientSettings = (new ClientSettingsExtractor($this->config, $this->projectRootPath()))->extract();
        $blang = (new BLangExtractor($this->config, $this->projectRootPath()))->extract();
        $systemFeatures = (new SystemFeaturesDetector($this->config, $this->projectRootPath()))->detect();

        return [
            'generated_at' => date(DATE_ATOM),
            'project' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) $this->cfg('project_fallback', 'evolutioncms-project'),
            'templates' => $rows,
            'tv_catalog' => $tvCatalog,
            'client_settings' => $clientSettings,
            'blang' => $blang,
            'system_features' => $systemFeatures,
            'stats' => $stats,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    public function writePayload(array $payload, string $outputDir, string $format): array
    {
        $normalizedFormat = strtolower($format);
        if (!in_array($normalizedFormat, ['json', 'md', 'php', 'all'], true)) {
            throw new RuntimeException('Invalid output format. Allowed: json|md|php|all');
        }

        $path = $this->normalizeOutputPath($outputDir);
        if (!$this->ensureDirectory($path)) {
            throw new RuntimeException('Failed to create output directory: ' . $path);
        }

        return $this->writeOutputs($payload, $path, $normalizedFormat);
    }

    /** @return array{class:string,source:string} */
    private function resolveControllerMeta(object $template, string $alias): array
    {
        $raw = trim((string) ($template->templatecontroller ?? ''));
        if ($raw !== '') {
            return [
                'class' => $this->normalizeControllerClass($raw),
                'source' => 'templatecontroller',
            ];
        }

        $namespace = trim((string) $this->cfg('fallback_controller_namespace', 'EvolutionCMS\\Main\\Controllers'), '\\');
        $studly = Str::studly(str_replace(['-', '_'], ' ', $alias));
        return [
            'class' => $namespace . '\\' . $studly . 'Controller',
            'source' => 'convention',
        ];
    }

    private function normalizeControllerClass(string $raw): string
    {
        if (Str::startsWith($raw, '\\')) {
            return ltrim($raw, '\\');
        }

        if (Str::startsWith($raw, 'EvolutionCMS\\')) {
            return $raw;
        }

        foreach ((array) $this->cfg('controller_prefix_map', []) as $prefix => $replacement) {
            if (Str::startsWith($raw, (string) $prefix)) {
                return (string) $replacement . substr($raw, strlen((string) $prefix));
            }
        }

        if (Str::contains($raw, '\\')) {
            return $raw;
        }

        $namespace = trim((string) $this->cfg('fallback_controller_namespace', 'EvolutionCMS\\Main\\Controllers'), '\\');
        return $namespace . '\\' . $raw;
    }

    private function resolveControllerPath(string $class): ?string
    {
        $normalized = ltrim($class, '\\');

        foreach ((array) $this->cfg('controller_namespace_paths', []) as $namespace => $basePath) {
            $prefix = rtrim((string) $namespace, '\\') . '\\';
            if (Str::startsWith($normalized, $prefix)) {
                $relative = str_replace('\\', '/', substr($normalized, strlen($prefix))) . '.php';
                return $this->projectRootPath((string) $basePath . $relative);
            }
        }

        if (class_exists($normalized)) {
            try {
                $reflection = new \ReflectionClass($normalized);
                $file = $reflection->getFileName();
                return $file ?: null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /** @return array{names:array<int,string>,source:string} */
    private function resolveViewMeta(string $explicitView, string $alias): array
    {
        if (trim($explicitView) !== '') {
            return [
                'names' => [trim($explicitView)],
                'source' => 'templateview',
            ];
        }

        return [
            'names' => $this->resolveConventionalViewNames($alias),
            'source' => 'convention',
        ];
    }

    /** @param array{names:array<int,string>,source:string} $view */
    private function resolveViewPath(array $view): array
    {
        $resolved = $this->resolveViewCandidates((array) ($view['names'] ?? []));

        foreach ($resolved as [$absolutePath, $name]) {
            if (is_file($absolutePath)) {
                return [$absolutePath, $name];
            }
        }

        return $resolved[0] ?? [$this->projectRootPath('views/' . trim((string) ($view['names'][0] ?? 'unknown.blade.php'), '/')), (string) ($view['names'][0] ?? '')];
    }

    /** @return array<int,string> */
    private function resolveConventionalViewNames(string $alias): array
    {
        $rawAlias = trim($alias);
        $normalizedAlias = (bool) $this->cfg('normalize_alias_dashes', true)
            ? str_replace('-', '_', $rawAlias)
            : $rawAlias;

        $pattern = (string) $this->cfg('fallback_view_pattern', 'views/{alias}.blade.php');
        $names = [str_replace('{alias}', $normalizedAlias, $pattern)];

        $alternateAlias = str_replace('_', '-', $rawAlias);
        $alternateName = str_replace('{alias}', $alternateAlias, $pattern);
        if (!in_array($alternateName, $names, true)) {
            $names[] = $alternateName;
        }

        return $names;
    }

    /**
     * @param array<int,mixed> $viewNames
     * @return array<int,array{0:string,1:string}>
     */
    private function resolveViewCandidates(array $viewNames): array
    {
        $resolved = [];
        foreach ($viewNames as $viewName) {
            if (!is_string($viewName) || trim($viewName) === '') {
                continue;
            }

            if (Str::startsWith($viewName, '/')) {
                $resolved[] = [$viewName, $viewName];
                continue;
            }

            $resolved[] = [$this->projectRootPath($viewName), $viewName];
        }

        return $resolved;
    }

    private function isPlaceholderView(string $path): bool
    {
        $content = (string) @file_get_contents($path);
        if (trim($content) === '') {
            return true;
        }

        $withoutComments = preg_replace([
            '/\{\{--.*?--\}\}/s',
            '/<!--.*?-->/s',
            '/\/\*.*?\*\//s',
            '/^\s*\/\/.*$/m',
            '/^\s*#.*$/m',
        ], '', $content);

        return trim((string) $withoutComments) === '';
    }

    /**
     * @param array<int,string> $tvColumns
     * @param array<int,string> $pivotColumns
     * @return array<int,array<string,mixed>>
     */
    private function loadTemplateTvs(string $pivotTable, string $tvTable, array $tvColumns, array $pivotColumns, int $templateId): array
    {
        $hasDefaultText = in_array('default_text', $tvColumns, true);
        $hasRank = in_array('rank', $pivotColumns, true);

        $query = DB::table($pivotTable . ' as p')
            ->join($tvTable . ' as t', 't.id', '=', 'p.tmplvarid')
            ->where('p.templateid', $templateId)
            ->select(['t.id', 't.name', 't.caption', 't.type', 't.elements']);

        if ($hasDefaultText) {
            $query->addSelect('t.default_text');
        }

        if ($hasRank) {
            $query->addSelect('p.rank');
            $query->orderBy('p.rank');
        }

        $rows = $query->orderBy('t.id')->get();

        $result = [];
        foreach ($rows as $row) {
            $item = [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'caption' => (string) ($row->caption ?? ''),
                'type' => (string) ($row->type ?? ''),
                'elements' => (string) ($row->elements ?? ''),
            ];

            if ($hasDefaultText) {
                $item['default_text'] = (string) ($row->default_text ?? '');
            }

            if ($hasRank) {
                $item['rank'] = isset($row->rank) ? (int) $row->rank : null;
            }

            $result[] = $item;
        }

        return $result;
    }

    private function normalizeOutputPath(string $path): string
    {
        if (Str::startsWith($path, '/')) {
            return $path;
        }

        return $this->projectRootPath($path);
    }

    private function projectRootPath(string $path = ''): string
    {
        $basePath = function_exists('base_path') ? base_path() : getcwd();
        $root = dirname((string) $basePath);

        if ($path === '') {
            return $root;
        }

        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

    private function toProjectRelativePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', rtrim($this->projectRootPath(), '/'));
        $rootWithSlash = $root . '/';

        if (Str::startsWith($normalizedPath, $rootWithSlash)) {
            return ltrim(substr($normalizedPath, strlen($root)), '/');
        }

        return $path;
    }

    private function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        return @mkdir($directory, 0775, true) || is_dir($directory);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    private function writeOutputs(array $payload, string $outputDir, string $format): array
    {
        $written = [];

        if ($format === 'all' || $format === 'json') {
            $jsonPath = $outputDir . '/templates.generated.json';
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $this->atomicWrite($jsonPath, (string) $json . PHP_EOL);
            $written[] = $jsonPath;
        }

        if ($format === 'all' || $format === 'php') {
            $phpPath = $outputDir . '/templates.generated.php';
            $php = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
            $this->atomicWrite($phpPath, $php);
            $written[] = $phpPath;
        }

        if ($format === 'all' || $format === 'md') {
            $mdPath = $outputDir . '/templates.generated.md';
            $md = $this->buildMarkdown($payload);
            $this->atomicWrite($mdPath, $md);
            $written[] = $mdPath;
        }

        return $written;
    }

    private function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $content, LOCK_EX);
        @chmod($tmp, 0664);
        rename($tmp, $path);
    }

    /** @param array<string,mixed> $payload */
    private function buildMarkdown(array $payload): string
    {
        $stats = (array) ($payload['stats'] ?? []);
        $templates = (array) ($payload['templates'] ?? []);
        $clientSettings = (array) ($payload['client_settings'] ?? []);
        $clientSettingsStats = (array) ($clientSettings['stats'] ?? []);
        $systemFeatures = (array) ($payload['system_features'] ?? []);

        $lines = [];
        $lines[] = '# Templates Registry';
        $lines[] = '';
        $lines[] = '- generated_at: ' . ($payload['generated_at'] ?? '');
        $lines[] = '- project: ' . ($payload['project'] ?? '');
        $lines[] = '- templates_total: ' . ($stats['templates_total'] ?? 0);
        $lines[] = '- missing_controllers: ' . ($stats['missing_controllers'] ?? 0);
        $lines[] = '- missing_views: ' . ($stats['missing_views'] ?? 0);
        $lines[] = '- placeholder_views: ' . ($stats['placeholder_views'] ?? 0);
        $lines[] = '- total_tvs_links: ' . ($stats['total_tvs_links'] ?? 0);
        $lines[] = '- unique_tvs: ' . ($stats['unique_tvs'] ?? 0);
        $lines[] = '- client_settings_exists: ' . (!empty($clientSettings['exists']) ? 'true' : 'false');
        $lines[] = '- client_settings_tabs_total: ' . ($clientSettingsStats['tabs_total'] ?? 0);
        $lines[] = '- multitv_installed: ' . ($this->featureInstalled($systemFeatures, 'multitv') ? 'true' : 'false');
        $lines[] = '- custom_tv_select_installed: ' . ($this->featureInstalled($systemFeatures, 'custom_tv_select') ? 'true' : 'false');
        $lines[] = '- templatesedit_installed: ' . ($this->featureInstalled($systemFeatures, 'templatesedit') ? 'true' : 'false');
        $lines[] = '- pagebuilder_installed: ' . ($this->featureInstalled($systemFeatures, 'pagebuilder') ? 'true' : 'false');
        $lines[] = '- blang_installed: ' . ($this->featureInstalled($systemFeatures, 'blang') ? 'true' : 'false');
        $lines[] = '';
        $lines[] = '| template id | alias | controller | view | tv count | flags |';
        $lines[] = '|---:|---|---|---|---:|---|';

        $missingControllers = [];
        $missingViews = [];
        $placeholderViews = [];

        foreach ($templates as $tpl) {
            if (!is_array($tpl)) {
                continue;
            }

            $flags = [];
            foreach ((array) ($tpl['flags'] ?? []) as $k => $v) {
                if ($v) {
                    $flags[] = (string) $k;
                }
            }
            $flagsText = $flags ? implode(', ', $flags) : '-';

            $lines[] = sprintf(
                '| %d | %s | %s | %s | %d | %s |',
                (int) ($tpl['id'] ?? 0),
                $this->escapeMd((string) ($tpl['alias'] ?? '')),
                $this->escapeMd((string) (($tpl['controller']['class'] ?? ''))),
                $this->escapeMd((string) (($tpl['view']['name'] ?? ''))),
                (int) ($tpl['tv_count'] ?? count((array) ($tpl['tv_refs'] ?? []))),
                $this->escapeMd($flagsText)
            );

            if (!empty($tpl['flags']['missing_controller'])) {
                $missingControllers[] = $tpl;
            }
            if (!empty($tpl['flags']['missing_view'])) {
                $missingViews[] = $tpl;
            }
            if (!empty($tpl['flags']['placeholder_view'])) {
                $placeholderViews[] = $tpl;
            }
        }

        $lines[] = '';
        $lines[] = '## missing controller';
        $lines[] = $this->problemList($missingControllers);
        $lines[] = '';
        $lines[] = '## missing view';
        $lines[] = $this->problemList($missingViews);
        $lines[] = '';
        $lines[] = '## placeholder view';
        $lines[] = $this->problemList($placeholderViews);
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function problemList(array $items): string
    {
        if (empty($items)) {
            return '- none';
        }

        $lines = [];
        foreach ($items as $tpl) {
            $lines[] = sprintf(
                '- #%d %s (%s)',
                (int) ($tpl['id'] ?? 0),
                (string) ($tpl['alias'] ?? ''),
                (string) ($tpl['name'] ?? '')
            );
        }

        return implode(PHP_EOL, $lines);
    }

    private function escapeMd(string $text): string
    {
        return str_replace('|', '\\|', $text);
    }

    /** @param array<string,mixed> $systemFeatures */
    private function featureInstalled(array $systemFeatures, string $key): bool
    {
        $feature = $systemFeatures[$key] ?? null;
        return is_array($feature) && !empty($feature['installed']);
    }

    /** @return array<int,string> */
    private function safeColumnListing(string $table): array
    {
        try {
            return Schema::getColumnListing($table);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int,string> $columns
     * @param array<int,string> $candidates
     */
    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveTableName(string $base): ?string
    {
        if (Schema::hasTable($base)) {
            return $base;
        }

        $defaultConnection = (string) config('database.default');
        $prefix = (string) config("database.connections.{$defaultConnection}.prefix", '');
        if ($prefix !== '') {
            $withPrefix = $prefix . $base;
            if (Schema::hasTable($withPrefix)) {
                return $withPrefix;
            }
        }

        return null;
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }
}
