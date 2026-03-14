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
    </style>
</head>
<body>
<div class="container container-body">
    <h1>
        <i class="fa fa-database"></i> Template Registry API
    </h1>

    <div id="actions">
        <div class="btn-group">
            <a class="btn btn-success" href="{{ $toggleUrl }}?enabled=1">
                <i class="fa fa-toggle-on"></i>
                <span>Enable API</span>
            </a>
            <a class="btn btn-danger" href="{{ $toggleUrl }}?enabled=0">
                <i class="fa fa-toggle-off"></i>
                <span>Disable API</span>
            </a>
        </div>
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
            <table class="table data">
                <tbody>
                <tr>
                    <td><strong>Current status</strong></td>
                    <td>
                        @if($enabled)
                            <span class="label label-success">enabled</span>
                        @else
                            <span class="label label-danger">disabled</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>API base endpoint</strong></td>
                    <td><code>{{ $apiPrefix }}</code></td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="sectionHeader">Access token</div>
        <div class="sectionBody">
            <form id="token-form" method="post" action="{{ $tokenUrl }}">
                @csrf
                <table class="table data">
                    <tbody>
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
                    </tbody>
                </table>
            </form>
        </div>

        <div class="sectionBody" style="margin-top:1rem;">
            API access remains protected by manager session. Token bypass uses header <code>X-Template-Registry-Token</code>.
        </div>

        <div class="sectionHeader">Auto-generate plugin</div>
        <div class="sectionBody">
            <table class="table data">
                <tbody>
                <tr>
                    <td><strong>Plugin status</strong></td>
                    <td>
                        @if(($pluginStatus['exists'] ?? false) === true)
                            @if(($pluginStatus['enabled'] ?? false) === true)
                                <span class="label label-success">enabled</span>
                            @else
                                <span class="label label-warning">disabled</span>
                            @endif
                            <span>#{{ (int) ($pluginStatus['id'] ?? 0) }} {{ (string) ($pluginStatus['name'] ?? '') }}</span>
                        @else
                            <span class="label label-danger">not installed</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Watched events</strong></td>
                    <td>{{ implode(', ', (array) ($pluginStatus['events_expected'] ?? [])) }}</td>
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
                </tr>
                @if(!empty($pluginStatus['events_unbound']))
                    <tr>
                        <td><strong>Unbound events</strong></td>
                        <td>{{ implode(', ', (array) $pluginStatus['events_unbound']) }}</td>
                    </tr>
                @endif
                @if(!empty($pluginStatus['events_missing_in_system']))
                    <tr>
                        <td><strong>Missing in system</strong></td>
                        <td>{{ implode(', ', (array) $pluginStatus['events_missing_in_system']) }}</td>
                    </tr>
                @endif
                </tbody>
            </table>

            <div class="btn-group">
                @if(($pluginStatus['exists'] ?? false) !== true)
                    <a class="btn btn-secondary" href="{{ $pluginInstallUrl }}">
                        <i class="fa fa-plug"></i>
                        <span>Install plugin (disabled)</span>
                    </a>
                @else
                    @if(($pluginStatus['enabled'] ?? false) === true)
                        <a class="btn btn-warning" href="{{ $pluginToggleUrl }}?enabled=0">
                            <i class="fa fa-pause"></i>
                            <span>Disable plugin</span>
                        </a>
                    @else
                        <a class="btn btn-success" href="{{ $pluginToggleUrl }}?enabled=1">
                            <i class="fa fa-play"></i>
                            <span>Enable plugin</span>
                        </a>
                    @endif
                    <a class="btn btn-secondary" href="{{ $pluginInstallUrl }}">
                        <i class="fa fa-refresh"></i>
                        <span>Reinstall plugin</span>
                    </a>
                @endif
            </div>

            <div style="margin-top:0.75rem;">
                Plugin regenerates registry files on <code>OnTVFormSave</code>, <code>OnTVFormDelete</code>, <code>OnTempFormSave</code>, <code>OnTempFormDelete</code>.
            </div>
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
        @endif
    @endif
</div>
</body>
</html>
