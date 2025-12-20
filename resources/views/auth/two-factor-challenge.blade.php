<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Two-Factor Authentication - Control Tower</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1d21 0%, #003D43 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .challenge-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
        }
        .code-input {
            font-size: 2rem;
            text-align: center;
            letter-spacing: 0.5em;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="challenge-card">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock-fill display-1 text-primary"></i>
            <h4 class="mt-3">Two-Factor Authentication</h4>
            <p class="text-muted">Enter the code from your authenticator app</p>
        </div>
        
        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        
        <form action="{{ route('2fa.verify') }}" method="POST">
            @csrf
            <div class="mb-4">
                <input type="text" name="code" class="form-control code-input" 
                       maxlength="8" required autofocus autocomplete="one-time-code"
                       placeholder="______">
                <small class="text-muted d-block text-center mt-2">
                    Or enter a recovery code
                </small>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-unlock me-2"></i>Verify
                </button>
                <a href="{{ route('logout') }}" class="btn btn-outline-secondary"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    Cancel Login
                </a>
            </div>
        </form>
        
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>
</body>
</html>
