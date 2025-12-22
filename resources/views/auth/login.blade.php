@extends('layouts.guest')

@section('title', 'Login')

@push('styles')
<style>
    body {
        background: linear-gradient(135deg, #001112 0%, #003D43 50%, #00A1AA 100%);
        min-height: 100vh;
    }
    
    .login-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }
    
    .login-header {
        background: linear-gradient(135deg, #003D43 0%, #00A1AA 100%);
        padding: 2.5rem;
        text-align: center;
        color: white;
    }
    
    .login-header .logo-icon {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 2.5rem;
    }
    
    .login-header h3 {
        font-weight: 700;
        margin-bottom: 0.25rem;
        letter-spacing: 0.5px;
    }
    
    .login-header p {
        opacity: 0.85;
        font-size: 0.95rem;
    }
    
    .login-body {
        padding: 2.5rem;
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        padding: 0.85rem 1rem;
        border: 2px solid #e0e0e0;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #00A1AA;
        box-shadow: 0 0 0 4px rgba(0, 161, 170, 0.1);
    }
    
    .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.5rem;
    }
    
    .btn-login {
        background: linear-gradient(135deg, #00A1AA 0%, #003D43 100%);
        border: none;
        border-radius: 10px;
        padding: 1rem;
        font-weight: 600;
        font-size: 1.05rem;
        letter-spacing: 0.5px;
        box-shadow: 0 8px 20px rgba(0, 161, 170, 0.3);
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 25px rgba(0, 161, 170, 0.4);
        background: linear-gradient(135deg, #00b5bf 0%, #004a50 100%);
    }
    
    .form-check-input:checked {
        background-color: #00A1AA;
        border-color: #00A1AA;
    }
    
    .alert-danger {
        border-radius: 10px;
        border-left: 4px solid #dc3545;
    }
    
    .input-icon {
        position: relative;
    }
    
    .input-icon i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #aaa;
    }
    
    .input-icon .form-control,
    .input-icon .form-select {
        padding-left: 2.75rem;
    }
    
    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        color: #aaa;
        font-size: 0.85rem;
        margin: 1.5rem 0;
    }
    
    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .divider span {
        padding: 0 1rem;
    }
    
    .footer-text {
        text-align: center;
        margin-top: 2rem;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.85rem;
    }
</style>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <h3>Control Tower</h3>
                <p class="mb-0">Workshop Management System</p>
            </div>
            
            <div class="login-body">
                @if($errors->any())
                <div class="alert alert-danger py-2 mb-4">
                    @foreach($errors->all() as $error)
                        <div><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</div>
                    @endforeach
                </div>
                @endif

                <form action="{{ route('login') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" value="{{ old('email') }}" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    {{-- Login Source is now auto-detected - trying internal DB first, then all LDAP servers --}}

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>
            </div>
        </div>
        
        <p class="footer-text">
            <i class="bi bi-shield-lock me-1"></i>Secure Login &bull; © {{ date('Y') }} Control Tower
        </p>
    </div>
</div>
@endsection
