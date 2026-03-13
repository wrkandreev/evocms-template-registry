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

    <p>Note: API access is additionally protected by manager access and optional token from package config.</p>
</div>
</body>
</html>
