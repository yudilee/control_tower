@extends('layouts.app')

@section('title', 'Security Settings')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-shield-lock me-2"></i>Security Settings</h1>
        <p class="text-muted">Manage two-factor authentication and active sessions</p>
    </div>
</div>

<div class="row g-4">
    <!-- 2FA Section -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>Two-Factor Authentication</h5>
            </div>
            <div class="card-body">
                @if($user->two_factor_enabled)
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Enabled</strong><br>
                            <small>Since {{ $user->two_factor_confirmed_at?->format('d M Y, H:i') }}</small>
                        </div>
                    </div>
                    
                    <p class="text-muted">
                        Your account is protected with two-factor authentication. You'll need to enter a code from 
                        your authenticator app when signing in.
                    </p>
                    
                    <hr>
                    
                    <h6><i class="bi bi-arrow-repeat me-2"></i>Recovery Codes</h6>
                    <p class="text-muted small">
                        Recovery codes can be used to access your account if you lose your authenticator device.
                    </p>
                    
                    <form action="{{ route('2fa.regenerate-codes') }}" method="POST" class="mb-3">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bi bi-arrow-clockwise me-1"></i>Regenerate Recovery Codes
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h6 class="text-danger"><i class="bi bi-x-circle me-2"></i>Disable 2FA</h6>
                    <form action="{{ route('2fa.disable') }}" method="POST" 
                          onsubmit="return confirm('Are you sure? This will make your account less secure.')">
                        @csrf
                        @method('DELETE')
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-shield-x me-1"></i>Disable Two-Factor Authentication
                        </button>
                    </form>
                @else
                    <div class="text-center py-4">
                        <i class="bi bi-shield-exclamation display-1 text-warning opacity-50 mb-3"></i>
                        <h5>Not Enabled</h5>
                        <p class="text-muted">
                            Add an extra layer of security to your account by enabling two-factor authentication.
                        </p>
                        <a href="{{ route('2fa.enable') }}" class="btn btn-primary btn-lg">
                            <i class="bi bi-shield-plus me-2"></i>Enable 2FA
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Active Sessions -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-display me-2"></i>Active Sessions</h5>
                <span class="badge bg-primary">{{ $sessions->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($sessions as $session)
                    <div class="list-group-item d-flex justify-content-between align-items-start py-3">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="bi bi-{{ $session->device_icon }} fs-3 text-{{ $session->is_current ? 'primary' : 'secondary' }}"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">
                                    {{ $session->browser }} on {{ $session->platform }}
                                    @if($session->is_current)
                                    <span class="badge bg-success ms-1">Current</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $session->ip_address }}
                                </small>
                                <br>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ $session->last_active_at?->diffForHumans() ?? 'Unknown' }}
                                </small>
                            </div>
                        </div>
                        @if(!$session->is_current)
                        <form action="{{ route('2fa.terminate-session', $session) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Terminate">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                    @empty
                    <div class="list-group-item text-center text-muted py-4">
                        No active sessions found
                    </div>
                    @endforelse
                </div>
            </div>
            @if($sessions->where('is_current', false)->count() > 0)
            <div class="card-footer">
                <form action="{{ route('2fa.terminate-other-sessions') }}" method="POST" class="row g-2 align-items-center">
                    @csrf
                    <div class="col">
                        <input type="password" name="password" class="form-control form-control-sm" placeholder="Confirm password" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-box-arrow-right me-1"></i>Log Out Other Sessions
                        </button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
