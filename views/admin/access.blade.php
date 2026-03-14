<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template Registry API Access</title>
    <style>
        body { font-family: sans-serif; background: #f3f5f7; color: #1f2937; margin: 0; }
        .wrap { max-width: 760px; margin: 40px auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .state { font-size: 18px; margin: 0 0 12px; }
        .ok { color: #0f766e; }
        .off { color: #b91c1c; }
        .controls { display: flex; gap: 12px; margin: 16px 0; }
        .btn { display: inline-block; padding: 10px 14px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn-on { background: #0f766e; color: #fff; }
        .btn-off { background: #b91c1c; color: #fff; }
        .token { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 18px; }
        .field { margin: 10px 0; }
        .label { display: block; font-weight: 600; margin-bottom: 6px; }
        .input { width: 100%; max-width: 520px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .hint { font-size: 13px; color: #64748b; margin: 8px 0 0; }
        .status { margin: 10px 0; padding: 10px 12px; border-radius: 8px; background: #ecfdf5; color: #065f46; }
        .btn-save { background: #0f172a; color: #fff; border: 0; cursor: pointer; }
        .btn-light { background: #e2e8f0; color: #0f172a; border: 0; cursor: pointer; }
        .actions { display: flex; gap: 10px; margin-top: 12px; }
        code { background: #eef2f7; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Template Registry API</h1>
    <p class="state">
        Status:
        @if($enabled)
            <strong class="ok">enabled</strong>
        @else
            <strong class="off">disabled</strong>
        @endif
    </p>

    <p>API base endpoint: <code>{{ $apiPrefix }}</code></p>

    <div class="controls">
        <a class="btn btn-on" href="{{ $toggleUrl }}?enabled=1">Enable API</a>
        <a class="btn btn-off" href="{{ $toggleUrl }}?enabled=0">Disable API</a>
    </div>

    @if(session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    <div class="token">
        <h2>Access token</h2>
        <form method="post" action="{{ $tokenUrl }}">
            @csrf
            <div class="field">
                <label class="label" for="access_token">X-Template-Registry-Token</label>
                <input class="input" id="access_token" name="access_token" type="text" value="{{ $token }}" autocomplete="off" maxlength="512">
                <p class="hint">Current source: <code>{{ $tokenSource }}</code>. Leave empty to disable token-based bypass.</p>
            </div>
            <div class="actions">
                <button class="btn btn-save" type="submit">Save token</button>
            </div>
        </form>
        <form method="post" action="{{ $tokenUrl }}">
            @csrf
            <input type="hidden" name="reset_to_config" value="1">
            <div class="actions">
                <button class="btn btn-light" type="submit">Reset to config token</button>
            </div>
        </form>
    </div>

    <p>Note: API access is protected by manager access. Token bypass uses header <code>X-Template-Registry-Token</code>.</p>
</div>
</body>
</html>
