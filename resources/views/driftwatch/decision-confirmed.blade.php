{{-- resources/views/driftwatch/decision-confirmed.blade.php --}}
{{-- Simple confirmation page shown after Teams approve/block callback --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decision Recorded — DriftWatch</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; }
        .card { background: #fff; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 420px; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 14px; }
        .approved { color: #10B981; }
        .blocked { color: #EF4444; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon {{ strtolower($action) === 'approved' ? 'approved' : 'blocked' }}">
            {{ strtolower($action) === 'approved' ? '&#10003;' : '&#10007;' }}
        </div>
        <div class="title">Decision Recorded</div>
        <div class="subtitle">PR #{{ $prNumber }} has been <strong class="{{ strtolower($action) === 'approved' ? 'approved' : 'blocked' }}">{{ strtoupper($action) }}</strong>.</div>
    </div>
</body>
</html>
