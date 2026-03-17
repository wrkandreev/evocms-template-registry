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
        .token-input { max-width: 520px; }
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
    @else
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
                        <div class="label">Resources shown</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">@if((bool) (($preview['client_settings']['exists'] ?? false))) yes @else no @endif</div>
                        <div class="label">ClientSettings</div>
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
                            resources shown: {{ (int) ($preview['resources_total'] ?? 0) }}
                        </td>
                    </tr>
                    </tbody>
                </table>
            @else
                <div class="alert alert-warning">Preview is unavailable.</div>
            @endif
        </div>

    @endif
</div>
</body>
</html>
