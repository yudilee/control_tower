@extends('layouts.app')

@section('title', 'Set Up 2FA')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>Set Up Two-Factor Authentication</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                </div>
                
                <div class="text-center py-4">
                    <!-- QR Code using a public API -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($otpauthUrl) }}" 
                         alt="2FA QR Code" class="img-fluid border rounded p-2 bg-white">
                </div>
                
                <div class="bg-light rounded p-3 mb-4">
                    <small class="text-muted d-block mb-1">Or enter this code manually:</small>
                    <code class="fs-5 user-select-all">{{ $secret }}</code>
                </div>
                
                <form action="{{ route('2fa.confirm') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Enter the 6-digit code from your app</label>
                        <input type="text" name="code" class="form-control form-control-lg text-center" 
                               maxlength="6" pattern="[0-9]{6}" required autofocus
                               placeholder="000000" style="letter-spacing: 0.5em;">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg me-2"></i>Verify & Enable
                        </button>
                        <a href="{{ route('2fa.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
