@extends('layouts.app')

@section('title', 'Recovery Codes')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Two-Factor Authentication Enabled!</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Save these recovery codes!</strong><br>
                    Store them in a secure place. Each code can only be used once.
                </div>
                
                <div class="bg-light rounded p-4 mb-4">
                    <div class="row row-cols-2 g-2">
                        @foreach($recoveryCodes as $code)
                        <div class="col">
                            <code class="d-block text-center py-2 bg-white rounded border user-select-all">{{ $code }}</code>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <div class="d-flex gap-2 mb-4">
                    <button onclick="copyRecoveryCodes()" class="btn btn-outline-primary flex-fill">
                        <i class="bi bi-clipboard me-2"></i>Copy
                    </button>
                    <button onclick="downloadRecoveryCodes()" class="btn btn-outline-primary flex-fill">
                        <i class="bi bi-download me-2"></i>Download
                    </button>
                </div>
                
                <div class="d-grid">
                    <a href="{{ route('2fa.index') }}" class="btn btn-success btn-lg">
                        <i class="bi bi-check-lg me-2"></i>Done
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const recoveryCodes = @json($recoveryCodes);

function copyRecoveryCodes() {
    navigator.clipboard.writeText(recoveryCodes.join('\n'));
    alert('Recovery codes copied to clipboard!');
}

function downloadRecoveryCodes() {
    const content = 'Control Tower Recovery Codes\n' + 
                   'Generated: ' + new Date().toLocaleString() + '\n\n' +
                   recoveryCodes.join('\n');
    const blob = new Blob([content], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'control-tower-recovery-codes.txt';
    a.click();
}
</script>
@endsection
