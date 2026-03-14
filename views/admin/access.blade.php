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
                    <span>Save token</span>
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
                        <td><strong>Header</strong></td>
                        <td><code>X-Template-Registry-Token</code></td>
                    </tr>
                    <tr>
                        <td><strong>Token value</strong></td>
                        <td>
                            <input class="form-control token-input" id="access_token" name="access_token" type="text" value="{{ $token }}" autocomplete="off" maxlength="512">
                            <small>Stored in <code>config/template-registry.php</code>.<br>Leave empty to disable token bypass.</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Plugin status</strong></td>
                        <td>
                            <select class="form-control field-select" name="plugin_state">
                                <option value="not_installed" @if(($pluginStatus['exists'] ?? false) !== true) selected @endif>Not installed</option>
                                <option value="disabled" @if(($pluginStatus['exists'] ?? false) === true && ($pluginStatus['enabled'] ?? false) === false) selected @endif>Disabled</option>
                                <option value="enabled" @if(($pluginStatus['enabled'] ?? false) === true) selected @endif>Enabled</option>
                            </select>
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
                    <tr>
                        <td><strong>Bound events</strong></td>
                    <td>
                        @if(!empty($pluginStatus['events_bound']))
                            {{ implode(', ', (array) $pluginStatus['events_bound']) }}
                        @else
                            -
                        @endif
                    </td>
                    <tr>
                        <td><strong>Unbound events</strong></td>
                        <td>
                            @if(!empty($pluginStatus['events_unbound']))
                                {{ implode(', ', (array) $pluginStatus['events_unbound']) }}
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
                <table class="table data">
                    <tbody>
                    <tr>
                        <td><strong>Generated at</strong></td>
                        <td>{{ $preview['generated_at'] ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Templates total</strong></td>
                        <td>{{ (int) ($preview['templates_total'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td><strong>TV total</strong></td>
                        <td>{{ (int) ($preview['tv_total'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Resources shown</strong></td>
                        <td>{{ (int) ($preview['resources_total'] ?? 0) }}</td>
                    </tr>
                    </tbody>
                </table>
            @else
                <div class="alert alert-warning">Preview is unavailable.</div>
            @endif
        </div>

        @if(is_array($preview))
            <div class="sectionHeader">Templates</div>
            <div class="sectionBody">
                @if(($preview['templates_truncated'] ?? false) === true)
                    <div class="alert alert-warning">Showing first 100 templates.</div>
                @endif
                <table class="table data">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Alias</th>
                        <th>TVs</th>
                        <th>Controller</th>
                        <th>View</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach((array) ($preview['templates'] ?? []) as $template)
                        <tr>
                            <td>{{ (int) ($template['id'] ?? 0) }}</td>
                            <td>{{ (string) ($template['name'] ?? '') }}</td>
                            <td>{{ (string) ($template['alias'] ?? '') }}</td>
                            <td>{{ (int) ($template['tv_count'] ?? 0) }}</td>
                            <td>
                                @if((bool) ($template['controller_exists'] ?? false))
                                    <span class="label label-success">ok</span>
                                @else
                                    <span class="label label-danger">missing</span>
                                @endif
                                <div class="mono">{{ (string) ($template['controller_class'] ?? '') }}</div>
                            </td>
                            <td>
                                @if((bool) ($template['view_exists'] ?? false))
                                    <span class="label label-success">ok</span>
                                @else
                                    <span class="label label-danger">missing</span>
                                @endif
                                <div class="mono">{{ (string) ($template['view_path'] ?? '') }}</div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="sectionHeader">TV catalog</div>
            <div class="sectionBody">
                @if(($preview['tv_truncated'] ?? false) === true)
                    <div class="alert alert-warning">Showing first 200 TVs.</div>
                @endif
                <table class="table data">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Caption</th>
                        <th>Type</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach((array) ($preview['tv_catalog'] ?? []) as $tv)
                        <tr>
                            <td>{{ (int) ($tv['id'] ?? 0) }}</td>
                            <td>{{ (string) ($tv['name'] ?? '') }}</td>
                            <td>{{ (string) ($tv['caption'] ?? '') }}</td>
                            <td>{{ (string) ($tv['type'] ?? '') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="sectionHeader">Resources</div>
            <div class="sectionBody">
                @if(($preview['resources_truncated'] ?? false) === true)
                    <div class="alert alert-warning">Showing first 100 resources.</div>
                @endif
                <table class="table data">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Page title</th>
                        <th>Alias</th>
                        <th>URI</th>
                        <th>Template</th>
                        <th>Published</th>
                        <th>Deleted</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach((array) ($preview['resources'] ?? []) as $resource)
                        <tr>
                            <td>{{ (int) ($resource['id'] ?? 0) }}</td>
                            <td>{{ (string) ($resource['pagetitle'] ?? '') }}</td>
                            <td>{{ (string) ($resource['alias'] ?? '') }}</td>
                            <td>{{ (string) ($resource['uri'] ?? '') }}</td>
                            <td>#{{ (int) ($resource['template_id'] ?? 0) }} {{ (string) ($resource['template_name'] ?? '') }}</td>
                            <td>
                                @if(($resource['published'] ?? null) === null)
                                    -
                                @elseif((bool) ($resource['published'] ?? false))
                                    <span class="label label-success">yes</span>
                                @else
                                    <span class="label label-warning">no</span>
                                @endif
                            </td>
                            <td>
                                @if(($resource['deleted'] ?? null) === null)
                                    -
                                @elseif((bool) ($resource['deleted'] ?? false))
                                    <span class="label label-danger">yes</span>
                                @else
                                    <span class="label label-success">no</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
</body>
</html>
