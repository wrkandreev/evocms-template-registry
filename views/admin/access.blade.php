<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template Registry API Access</title>
    <link rel="stylesheet" href="/manager/media/style/default/css/styles.min.css">
    <link rel="stylesheet" href="/manager/media/style/default/css/main.css">
    <style>
        body { background: #f5f5f5; }
        .container.container-body { max-width: 980px; }
        .table.data td:first-child { width: 240px; white-space: nowrap; }
        .token-mask { display: inline-block; min-width: 180px; letter-spacing: 0.16em; }
        .generated-token { margin-top: 0.75rem; }
        #actions { margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; }
        #actions .btn-group { display: flex; gap: 0.5rem; }
        .module-tabs { margin-bottom: 1rem; }
        .module-tabs .btn + .btn { margin-left: 0.5rem; }
        .mono { font-family: Menlo, Monaco, Consolas, "Courier New", monospace; font-size: 12px; word-break: break-all; }
        .field-select { max-width: 260px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; }
        .stat-card { border: 1px solid #dcdcdc; background: #fff; padding: 0.75rem 1rem; }
        .stat-card .value { font-size: 22px; font-weight: 700; line-height: 1.1; }
        .stat-card .value.is-yes { color: #2f7d32; }
        .stat-card .value.is-no { color: #a94442; }
        .stat-card .label { margin-top: 0.25rem; color: #666; }
    </style>
</head>
<body>
<div class="container container-body">
    <h1>
        <i class="fa fa-database"></i> Template Registry API
    </h1>

    <div id="actions">
        <div></div>
        @if($activeTab === 'access')
            <div class="btn-group">
                <button class="btn btn-primary" type="submit" form="token-form">
                    <i class="fa fa-save"></i>
                    <span>Save</span>
                </button>
            </div>
        @endif
    </div>

    <div class="module-tabs">
        <a class="btn {{ $activeTab === 'access' ? 'btn-primary' : 'btn-secondary' }}" href="{{ $accessTabUrl }}">
            <i class="fa fa-lock"></i>
            <span>Access</span>
        </a>
        <a class="btn {{ $activeTab === 'preview' ? 'btn-primary' : 'btn-secondary' }}" href="{{ $previewTabUrl }}">
            <i class="fa fa-list"></i>
            <span>Registry preview</span>
        </a>
        <a class="btn {{ $activeTab === 'guide' ? 'btn-primary' : 'btn-secondary' }}" href="{{ $guideTabUrl }}">
            <i class="fa fa-book"></i>
            <span>Guide</span>
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('statusError'))
        <div class="alert alert-danger">{{ session('statusError') }}</div>
    @endif
    @if(session('statusWarning'))
        <div class="alert alert-warning">{{ session('statusWarning') }}</div>
    @endif
    @if(session('generatedAccessToken'))
        <div class="alert alert-warning generated-token">
            <strong>Read token generated. Copy it now, it will not be shown again.</strong><br>
            <code class="mono">{{ session('generatedAccessToken') }}</code>
        </div>
    @endif
    @if(session('generatedWriteAccessToken'))
        <div class="alert alert-warning generated-token">
            <strong>Write token generated. Copy it now, it will not be shown again.</strong><br>
            <code class="mono">{{ session('generatedWriteAccessToken') }}</code>
        </div>
    @endif

@if($activeTab === 'access')
        <div class="sectionHeader">API Status</div>
        <div class="sectionBody">
            <form id="token-form" method="post" action="{{ $settingsUrl }}">
                @csrf
                <table class="table data">
                    <tbody>
                    <tr>
                        <td><strong>API status</strong></td>
                        <td>
                            <select class="form-control field-select" name="api_enabled">
                                <option value="enabled" @if($enabled) selected @endif>Enabled</option>
                                <option value="disabled" @if(!$enabled) selected @endif>Disabled</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>API base endpoint</strong></td>
                        <td><code>{{ $apiPrefix }}</code></td>
                    </tr>
                    <tr>
                        <td><strong>Token value</strong></td>
                        <td>
                            <input class="form-control token-input" id="access_token" name="access_token" type="text" value="{{ $token }}" autocomplete="off" maxlength="512">
                            <small>Stored in <code>custom/config/template-registry.php</code>.<br>Leave empty to disable token bypass.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Write API status</strong></td>
                        <td>
                            <select class="form-control field-select" name="write_enabled">
                                <option value="disabled" @if(!$writeEnabled) selected @endif>Disabled</option>
                                <option value="enabled" @if($writeEnabled) selected @endif>Enabled</option>
                            </select>
                            <small>Disabled by default. Enables create/update endpoints for templates, TVs and resources.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Write token value</strong></td>
                        <td>
                            <input class="form-control token-input" id="write_access_token" name="write_access_token" type="text" value="{{ $writeToken }}" autocomplete="off" maxlength="512">
                            <small>Header: <code>X-Template-Registry-Write-Token</code>.<br>Leave empty to allow writes only from active manager session.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Plugin status</strong></td>
                        <td>
                            @if(($pluginStatus['exists'] ?? false) === true)
                                <select class="form-control field-select" name="plugin_state">
                                    <option value="disabled" @if(($pluginStatus['enabled'] ?? false) === false) selected @endif>Disabled</option>
                                    <option value="enabled" @if(($pluginStatus['enabled'] ?? false) === true) selected @endif>Enabled</option>
                                </select>
                            @else
                                <button class="btn btn-secondary" type="submit" name="plugin_state" value="disabled">
                                    <i class="fa fa-plug"></i>
                                    <span>Install plugin</span>
                                </button>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Plugin record</strong></td>
                        <td>
                            @if(($pluginStatus['exists'] ?? false) === true)
                                #{{ (int) ($pluginStatus['id'] ?? 0) }} {{ (string) ($pluginStatus['name'] ?? '') }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @if(!empty($pluginStatus['events_missing_in_system']))
                    <tr>
                        <td><strong>Missing in system</strong></td>
                        <td>{{ implode(', ', (array) $pluginStatus['events_missing_in_system']) }}</td>
                    </tr>
                @endif
                </tbody>
            </table>
            </form>
        </div>
    @elseif($activeTab === 'preview')
        <div class="sectionHeader">Registry preview</div>
        <div class="sectionBody">
            @if($previewError)
                <div class="alert alert-danger">{{ $previewError }}</div>
            @elseif(is_array($preview))
                <div class="stats-grid" style="margin-bottom:1rem;">
                    <div class="stat-card">
                        <div class="value">{{ (int) ($preview['templates_total'] ?? 0) }}</div>
                        <div class="label">Templates</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">{{ (int) ($preview['tv_total'] ?? 0) }}</div>
                        <div class="label">TVs</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">{{ (int) ($preview['resources_total'] ?? 0) }}</div>
                        <div class="label">Resources total</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">{{ (int) (($preview['client_settings']['stats']['fields_total'] ?? 0)) }}</div>
                        <div class="label">ClientSettings fields</div>
                    </div>
                </div>
                @php($features = (array) ($preview['system_features'] ?? []))
                <div class="stats-grid" style="margin-bottom:1rem;">
                    <div class="stat-card">
                        <div class="value @if(!empty($features['client_settings']['installed'])) is-yes @else is-no @endif">@if(!empty($features['client_settings']['installed'])) yes @else no @endif</div>
                        <div class="label">ClientSettings</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['multitv']['installed'])) is-yes @else is-no @endif">@if(!empty($features['multitv']['installed'])) yes @else no @endif</div>
                        <div class="label">MultiTV</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['custom_tv_select']['installed'])) is-yes @else is-no @endif">@if(!empty($features['custom_tv_select']['installed'])) yes @else no @endif</div>
                        <div class="label">Custom TV Select</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['templatesedit']['installed'])) is-yes @else is-no @endif">@if(!empty($features['templatesedit']['installed'])) yes @else no @endif</div>
                        <div class="label">TemplatesEdit</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['pagebuilder']['installed'])) is-yes @else is-no @endif">@if(!empty($features['pagebuilder']['installed'])) yes @else no @endif</div>
                        <div class="label">PageBuilder</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['simplegallery']['installed'])) is-yes @else is-no @endif">@if(!empty($features['simplegallery']['installed'])) yes @else no @endif</div>
                        <div class="label">SimpleGallery</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['blang']['installed'])) is-yes @else is-no @endif">@if(!empty($features['blang']['installed'])) yes @else no @endif</div>
                        <div class="label">bLang</div>
                    </div>
                </div>
                <table class="table data">
                    <tbody>
                    <tr>
                        <td><strong>Generated at</strong></td>
                        <td>{{ $preview['generated_at'] ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Project stats</strong></td>
                        <td>
                            templates: {{ (int) ($preview['templates_total'] ?? 0) }},
                            tvs: {{ (int) ($preview['tv_total'] ?? 0) }},
                            resources total: {{ (int) ($preview['resources_total'] ?? 0) }},
                            resources shown in preview: {{ (int) ($preview['resources_shown'] ?? 0) }}
                        </td>
                    </tr>
                    </tbody>
                </table>
            @else
                <div class="alert alert-warning">Preview is unavailable.</div>
            @endif
        </div>
    @elseif($activeTab === 'guide')
        <div class="sectionHeader">How to use Template Registry</div>
        <div class="sectionBody">
            <p><strong>Template Registry</strong> — это API и генератор реестра для Evolution CMS. Он связывает шаблоны, контроллеры, view-файлы и TV, и даёт HTTP API для чтения и записи.</p>

            <h3 style="margin-top:1.5rem;">1. API access</h3>
            <p>API по умолчанию требует manager-сессию. Включите/выключите на вкладке <strong>Access</strong>.</p>
            <p>Для локальных инструментов (CLI, AI-агенты) можно сгенерировать read token на вкладке <strong>Access</strong> и передавать его в заголовке <code>X-Template-Registry-Token</code>. Токен показывается только один раз после генерации.</p>

            <h3 style="margin-top:1.5rem;">2. Basic read flow</h3>
            <ol>
                <li><code>GET /api/template-registry/stats</code> — проверить, что реестр работает</li>
                <li><code>GET /api/template-registry/templates</code> — посмотреть шаблоны и их TV</li>
                <li><code>GET /api/template-registry/agent-manifest</code> — полная инструкция для AI-агента</li>
            </ol>
            <p>Чтобы получить контекст конкретной страницы:</p>
            <ol>
                <li><code>GET /api/template-registry/resource-resolve?url=/o-kompanii</code> — получить ID ресурса по URL</li>
                <li><code>GET /api/template-registry/resource-context?resource_id=7</code> — полный контекст: мета, шаблон, TV и их значения</li>
            </ol>

            <h3 style="margin-top:1.5rem;">3. Write API</h3>
            <p>Write API выключен по умолчанию. Включите на вкладке <strong>Access</strong> (флаг <em>Write API status</em>).</p>
            <p>Запись доступна из manager-сессии или по заголовку <code>X-Template-Registry-Write-Token</code>.</p>
            <p>После успешной записи реестр перегенерируется автоматически.</p>

            <h3 style="margin-top:1.5rem;">4. CLI commands</h3>
            <p>Основные artisan-команды (выполнять из <code>core/</code>):</p>
            <table class="table data">
                <tbody>
                <tr><td>Generate registry files</td><td><code>php artisan template-registry:generate</code></td></tr>
                <tr><td>Create content migration</td><td><code>php artisan template-registry:migrate:make MigrationName</code></td></tr>
                <tr><td>Apply migrations</td><td><code>php artisan template-registry:migrate</code></td></tr>
                <tr><td>Migration status</td><td><code>php artisan template-registry:migrate:status</code></td></tr>
                <tr><td>Install/remove module</td><td><code>template-registry:module:install / uninstall</code></td></tr>
                <tr><td>Install/remove plugin</td><td><code>template-registry:plugin:install / uninstall</code></td></tr>
                <tr><td>Install/remove API routes</td><td><code>template-registry:routes:install / uninstall</code></td></tr>
                </tbody>
            </table>

            <h3 style="margin-top:1.5rem;">5. Registry preview</h3>
            <p>На вкладке <strong>Registry preview</strong> отображается статистика: количество шаблонов, TV, ресурсов, ClientSettings-полей и статус установленных расширений (MultiTV, PageBuilder, bLang и др.).</p>

            <h3 style="margin-top:1.5rem;">6. Auto-generate plugin</h3>
            <p>Плагин автоматически перегенерирует реестр при сохранении TV или шаблонов в админке. Установите его на вкладке <strong>Access</strong> (кнопка <em>Install plugin</em>) и включите.</p>

            <h3 style="margin-top:1.5rem;">7. Curl examples</h3>
            <pre class="mono" style="background:#f9f9f9;border:1px solid #ddd;padding:0.75rem;overflow-x:auto;">
# Полный реестр
curl {{ $apiPrefix }}/

# Статистика
curl {{ $apiPrefix }}/stats

# Все шаблоны
curl {{ $apiPrefix }}/templates

# Инструкция для агента
curl {{ $apiPrefix }}/agent-manifest

# Ресурс по URL
curl "{{ $apiPrefix }}/resource-resolve?url=/o-kompanii"

# Контекст страницы
curl "{{ $apiPrefix }}/resource-context?resource_id=7"

# С токеном
curl -H "X-Template-Registry-Token: your-token" {{ $apiPrefix }}/stats

# Запись (с write-токеном)
curl -X POST {{ $apiPrefix }}/templates \
  -H "X-Template-Registry-Write-Token: your-write-token" \
  -H "Content-Type: application/json" \
  -d '{"name":"Landing","alias":"landing"}'

# TV values для ресурса
curl -X PUT {{ $apiPrefix }}/resources/7/tv-values \
  -H "X-Template-Registry-Write-Token: your-write-token" \
  -H "Content-Type: application/json" \
  -d '{"values":{"1":"new value","hero_title":"Hello"}}'
            </pre>

            <p style="margin-top:1rem;">Подробнее — в <code>README.md</code> и <code>AGENTS.md</code> пакета.</p>
        </div>
    @endif
                            <button class="btn btn-secondary" type="submit" name="generate_access_token" value="1">
                                <i class="fa fa-refresh"></i>
                                <span>Generate new</span>
                            </button>
                            <small>Header: <code>X-Template-Registry-Token</code>.<br>Generated token is shown only once after saving.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Write API status</strong></td>
                        <td>
                            <select class="form-control field-select" name="write_enabled">
                                <option value="disabled" @if(!$writeEnabled) selected @endif>Disabled</option>
                                <option value="enabled" @if($writeEnabled) selected @endif>Enabled</option>
                            </select>
                            <small>Disabled by default. Enables create/update endpoints for templates, TVs and resources.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Write token</strong></td>
                        <td>
                            @if($writeToken !== '')
                                <span class="mono token-mask">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                            @else
                                <span>-</span>
                            @endif
                            <button class="btn btn-secondary" type="submit" name="generate_write_access_token" value="1" onclick="return confirm('Generate a new write token? The previous write token will stop working.');">
                                <i class="fa fa-refresh"></i>
                                <span>Generate new</span>
                            </button>
                            <small>Header: <code>X-Template-Registry-Write-Token</code>.<br>Generated token is shown only once after saving.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Plugin status</strong></td>
                        <td>
                            @if(($pluginStatus['exists'] ?? false) === true)
                                <select class="form-control field-select" name="plugin_state">
                                    <option value="disabled" @if(($pluginStatus['enabled'] ?? false) === false) selected @endif>Disabled</option>
                                    <option value="enabled" @if(($pluginStatus['enabled'] ?? false) === true) selected @endif>Enabled</option>
                                </select>
                            @else
                                <button class="btn btn-secondary" type="submit" name="plugin_state" value="disabled">
                                    <i class="fa fa-plug"></i>
                                    <span>Install plugin</span>
                                </button>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Plugin record</strong></td>
                        <td>
                            @if(($pluginStatus['exists'] ?? false) === true)
                                #{{ (int) ($pluginStatus['id'] ?? 0) }} {{ (string) ($pluginStatus['name'] ?? '') }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @if(!empty($pluginStatus['events_missing_in_system']))
                    <tr>
                        <td><strong>Missing in system</strong></td>
                        <td>{{ implode(', ', (array) $pluginStatus['events_missing_in_system']) }}</td>
                    </tr>
                @endif
                </tbody>
            </table>
            </form>
        </div>
    @elseif($activeTab === 'preview')
        <div class="sectionHeader">Registry preview</div>
        <div class="sectionBody">
            @if($previewError)
                <div class="alert alert-danger">{{ $previewError }}</div>
            @elseif(is_array($preview))
                <div class="stats-grid" style="margin-bottom:1rem;">
                    <div class="stat-card">
                        <div class="value">{{ (int) ($preview['templates_total'] ?? 0) }}</div>
                        <div class="label">Templates</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">{{ (int) ($preview['tv_total'] ?? 0) }}</div>
                        <div class="label">TVs</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">{{ (int) ($preview['resources_total'] ?? 0) }}</div>
                        <div class="label">Resources total</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">{{ (int) (($preview['client_settings']['stats']['fields_total'] ?? 0)) }}</div>
                        <div class="label">ClientSettings fields</div>
                    </div>
                </div>
                @php($features = (array) ($preview['system_features'] ?? []))
                <div class="stats-grid" style="margin-bottom:1rem;">
                    <div class="stat-card">
                        <div class="value @if(!empty($features['client_settings']['installed'])) is-yes @else is-no @endif">@if(!empty($features['client_settings']['installed'])) yes @else no @endif</div>
                        <div class="label">ClientSettings</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['multitv']['installed'])) is-yes @else is-no @endif">@if(!empty($features['multitv']['installed'])) yes @else no @endif</div>
                        <div class="label">MultiTV</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['custom_tv_select']['installed'])) is-yes @else is-no @endif">@if(!empty($features['custom_tv_select']['installed'])) yes @else no @endif</div>
                        <div class="label">Custom TV Select</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['templatesedit']['installed'])) is-yes @else is-no @endif">@if(!empty($features['templatesedit']['installed'])) yes @else no @endif</div>
                        <div class="label">TemplatesEdit</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['pagebuilder']['installed'])) is-yes @else is-no @endif">@if(!empty($features['pagebuilder']['installed'])) yes @else no @endif</div>
                        <div class="label">PageBuilder</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['simplegallery']['installed'])) is-yes @else is-no @endif">@if(!empty($features['simplegallery']['installed'])) yes @else no @endif</div>
                        <div class="label">SimpleGallery</div>
                    </div>
                    <div class="stat-card">
                        <div class="value @if(!empty($features['blang']['installed'])) is-yes @else is-no @endif">@if(!empty($features['blang']['installed'])) yes @else no @endif</div>
                        <div class="label">bLang</div>
                    </div>
                </div>
                <table class="table data">
                    <tbody>
                    <tr>
                        <td><strong>Generated at</strong></td>
                        <td>{{ $preview['generated_at'] ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Project stats</strong></td>
                        <td>
                            templates: {{ (int) ($preview['templates_total'] ?? 0) }},
                            tvs: {{ (int) ($preview['tv_total'] ?? 0) }},
                            resources total: {{ (int) ($preview['resources_total'] ?? 0) }},
                            resources shown in preview: {{ (int) ($preview['resources_shown'] ?? 0) }}
                        </td>
                    </tr>
                    </tbody>
                </table>
            @else
                <div class="alert alert-warning">Preview is unavailable.</div>
            @endif
        </div>

    @elseif($activeTab === 'guide')
        <div class="sectionHeader">How to use Template Registry</div>
        <div class="sectionBody">
            <p><strong>Template Registry</strong> — это API и генератор реестра для Evolution CMS. Он связывает шаблоны, контроллеры, view-файлы и TV, и даёт HTTP API для чтения и записи.</p>

            <h3 style="margin-top:1.5rem;">1. API access</h3>
            <p>API по умолчанию требует manager-сессию. Включите/выключите на вкладке <strong>Access</strong>.</p>
            <p>Для локальных инструментов (CLI, AI-агенты) можно сгенерировать read token на вкладке <strong>Access</strong> и передавать его в заголовке <code>X-Template-Registry-Token</code>. Токен показывается только один раз после генерации.</p>

            <h3 style="margin-top:1.5rem;">2. Basic read flow</h3>
            <ol>
                <li><code>GET /api/template-registry/stats</code> — проверить, что реестр работает</li>
                <li><code>GET /api/template-registry/templates</code> — посмотреть шаблоны и их TV</li>
                <li><code>GET /api/template-registry/agent-manifest</code> — полная инструкция для AI-агента</li>
            </ol>
            <p>Чтобы получить контекст конкретной страницы:</p>
            <ol>
                <li><code>GET /api/template-registry/resource-resolve?url=/o-kompanii</code> — получить ID ресурса по URL</li>
                <li><code>GET /api/template-registry/resource-context?resource_id=7</code> — полный контекст: мета, шаблон, TV и их значения</li>
            </ol>

            <h3 style="margin-top:1.5rem;">3. Write API</h3>
            <p>Write API выключен по умолчанию. Включите на вкладке <strong>Access</strong> (флаг <em>Write API status</em>).</p>
            <p>Запись доступна из manager-сессии или по заголовку <code>X-Template-Registry-Write-Token</code>.</p>
            <p>После успешной записи реестр перегенерируется автоматически.</p>

            <h3 style="margin-top:1.5rem;">4. CLI commands</h3>
            <p>Основные artisan-команды (выполнять из <code>core/</code>):</p>
            <table class="table data">
                <tbody>
                <tr><td>Generate registry files</td><td><code>php artisan template-registry:generate</code></td></tr>
                <tr><td>Create content migration</td><td><code>php artisan template-registry:migrate:make MigrationName</code></td></tr>
                <tr><td>Apply migrations</td><td><code>php artisan template-registry:migrate</code></td></tr>
                <tr><td>Migration status</td><td><code>php artisan template-registry:migrate:status</code></td></tr>
                <tr><td>Install/remove module</td><td><code>template-registry:module:install / uninstall</code></td></tr>
                <tr><td>Install/remove plugin</td><td><code>template-registry:plugin:install / uninstall</code></td></tr>
                <tr><td>Install/remove API routes</td><td><code>template-registry:routes:install / uninstall</code></td></tr>
                </tbody>
            </table>

            <h3 style="margin-top:1.5rem;">5. Registry preview</h3>
            <p>На вкладке <strong>Registry preview</strong> отображается статистика: количество шаблонов, TV, ресурсов, ClientSettings-полей и статус установленных расширений (MultiTV, PageBuilder, bLang и др.).</p>

            <h3 style="margin-top:1.5rem;">6. Auto-generate plugin</h3>
            <p>Плагин автоматически перегенерирует реестр при сохранении TV или шаблонов в админке. Установите его на вкладке <strong>Access</strong> (кнопка <em>Install plugin</em>) и включите.</p>

            <h3 style="margin-top:1.5rem;">7. Curl examples</h3>
            <pre class="mono" style="background:#f9f9f9;border:1px solid #ddd;padding:0.75rem;overflow-x:auto;">
# Полный реестр
curl {{ $apiPrefix }}/

# Статистика
curl {{ $apiPrefix }}/stats

# Все шаблоны
curl {{ $apiPrefix }}/templates

# Инструкция для агента
curl {{ $apiPrefix }}/agent-manifest

# Ресурс по URL
curl "{{ $apiPrefix }}/resource-resolve?url=/o-kompanii"

# Контекст страницы
curl "{{ $apiPrefix }}/resource-context?resource_id=7"

# С токеном
curl -H "X-Template-Registry-Token: your-token" {{ $apiPrefix }}/stats

# Запись (с write-токеном)
curl -X POST {{ $apiPrefix }}/templates \
  -H "X-Template-Registry-Write-Token: your-write-token" \
  -H "Content-Type: application/json" \
  -d '{"name":"Landing","alias":"landing"}'

# TV values для ресурса
curl -X PUT {{ $apiPrefix }}/resources/7/tv-values \
  -H "X-Template-Registry-Write-Token: your-write-token" \
  -H "Content-Type: application/json" \
  -d '{"values":{"1":"new value","hero_title":"Hello"}}'
            </pre>

            <p style="margin-top:1rem;">Подробнее — в <code>README.md</code> и <code>AGENTS.md</code> пакета.</p>
        </div>

    @endif
</div>
</body>
</html>
