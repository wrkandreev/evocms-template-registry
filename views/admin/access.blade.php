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
        #actions { margin-bottom: 1rem; }
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
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('statusError'))
        <div class="alert alert-danger">{{ session('statusError') }}</div>
    @endif

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
        <form method="post" action="{{ $tokenUrl }}">
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
                        <small>Stored in <code>config/template-registry.php</code>. Leave empty to disable token bypass.</small>
                    </td>
                </tr>
                </tbody>
            </table>

            <button class="btn btn-primary" type="submit">
                <i class="fa fa-save"></i>
                <span>Save token</span>
            </button>
        </form>
    </div>

    <div class="sectionBody" style="margin-top:1rem;">
        API access remains protected by manager session. Token bypass uses header <code>X-Template-Registry-Token</code>.
    </div>
</div>
</body>
</html>
