<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DriftWatch — Sign In</title>
    @include('partials.styles')
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #0f172a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .login-card { background: #1e1e2e; border: 1px solid #313244; border-radius: 16px; padding: 40px; width: 420px; max-width: 95vw; box-shadow: 0 24px 48px rgba(0,0,0,0.4); }
        .login-card h2 { color: #cdd6f4; font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .login-card .subtitle { color: #6c7086; font-size: 13px; margin-bottom: 28px; }
        .login-card label { color: #a6adc8; font-size: 12px; font-weight: 600; margin-bottom: 6px; display: block; }
        .login-card input { background: #181825; color: #cdd6f4; border: 1px solid #313244; border-radius: 10px; padding: 10px 14px; width: 100%; font-size: 13px; box-sizing: border-box; }
        .login-card input:focus { outline: none; border-color: #605DFF; box-shadow: 0 0 0 3px rgba(96,93,255,0.15); }
        .login-card .btn-login { background: #605DFF; color: #fff; border: none; border-radius: 10px; padding: 11px; width: 100%; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .login-card .btn-login:hover { background: #4f4cd9; }
        .login-card .error { color: #f38ba8; font-size: 12px; margin-bottom: 12px; background: rgba(243,139,168,0.1); padding: 8px 12px; border-radius: 8px; }
        .demo-accounts { margin-top: 24px; padding-top: 20px; border-top: 1px solid #313244; }
        .demo-accounts h6 { color: #6c7086; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .demo-btn { background: #181825; border: 1px solid #313244; border-radius: 8px; padding: 8px 12px; width: 100%; text-align: left; cursor: pointer; color: #cdd6f4; font-size: 12px; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; transition: all 0.15s; }
        .demo-btn:hover { border-color: #605DFF; background: #1e1e2e; }
        .demo-btn .avatar { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .demo-btn .role { color: #6c7086; font-size: 10px; }
        .logo-row { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
        .logo-row .icon { width: 36px; height: 36px; border-radius: 10px; background: #605DFF; display: flex; align-items: center; justify-content: center; }
        .logo-row .icon span { color: #fff; font-size: 20px; }
        .logo-row .name { color: #cdd6f4; font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-row">
            <img src="{{ asset('assets/driftwatch logo.png') }}" alt="DriftWatch" style="height: 36px; width: auto; border-radius: 10px;">
            <span class="name">DriftWatch</span>
        </div>
        <h2>Sign in</h2>
        <p class="subtitle">Pre-deployment risk intelligence platform</p>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <div style="margin-bottom: 16px;">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="you@driftwatch.dev" required autofocus>
            </div>
            <div style="margin-bottom: 20px;">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="demo-accounts">
            <h6>Quick Demo Access</h6>
            <button class="demo-btn" onclick="fillLogin('admin@driftwatch.dev','password')">
                <div class="avatar" style="background:#605DFF;">AU</div>
                <div><strong>Admin User</strong> <span class="role">— Full access (admin)</span></div>
            </button>
            <button class="demo-btn" onclick="fillLogin('sarah@driftwatch.dev','password')">
                <div class="avatar" style="background:#E91E63;">SC</div>
                <div><strong>Sarah Chen</strong> <span class="role">— Reviewer (approve/edit)</span></div>
            </button>
            <button class="demo-btn" onclick="fillLogin('viewer@driftwatch.dev','password')">
                <div class="avatar" style="background:#4CAF50;">DV</div>
                <div><strong>Demo Viewer</strong> <span class="role">— Read only</span></div>
            </button>
        </div>
    </div>
    <script>
        function fillLogin(email, pass) {
            document.querySelector('input[name="email"]').value = email;
            document.querySelector('input[name="password"]').value = pass;
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>
