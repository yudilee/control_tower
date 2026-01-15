@extends('layouts.app')

@section('title', isset($partOrder) ? 'Edit Part Order' : 'Add Part Order')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-box-seam me-2"></i>
                        {{ isset($partOrder) ? 'Edit Part Order' : 'Add Part Order' }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($partOrder) ? route('part-orders.update', $partOrder) : route('part-orders.store') }}" 
                          method="POST">
                        @csrf
                        @if(isset($partOrder))
                            @method('PUT')
                        @endif

                        <!-- Job Selection (only for new orders) -->
                        @if(!isset($partOrder))
                        <div class="mb-3">
                            <label for="job_id" class="form-label">Job <span class="text-danger">*</span></label>
                            @if(isset($job))
                                <input type="hidden" name="job_id" value="{{ $job->id }}">
                                <div class="form-control bg-light">
                                    <strong>{{ $job->job_number }}</strong> - {{ $job->customer_name ?? 'No customer' }}
                                    <br><small class="text-muted">{{ $job->plate_number }}</small>
                                </div>
                            @else
                                <select name="job_id" id="job_id" class="form-select @error('job_id') is-invalid @enderror" required>
                                    <option value="">Select a job that needs parts...</option>
                                    @forelse($jobs as $jobOption)
                                        <option value="{{ $jobOption->id }}" {{ old('job_id') == $jobOption->id ? 'selected' : '' }}>
                                            {{ $jobOption->job_number }} - {{ $jobOption->customer_name ?? 'No customer' }} ({{ $jobOption->plate_number }})
                                        </option>
                                    @empty
                                        <option value="" disabled>No jobs with "Needs Parts" found</option>
                                    @endforelse
                                </select>
                                <div class="form-text">Only showing uninvoiced jobs marked as "Needs Parts"</div>
                                @error('job_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        @else
                        <div class="mb-3">
                            <label class="form-label">Job</label>
                            <div class="form-control bg-light">
                                <a href="{{ route('jobs.show', $partOrder->job_id) }}">
                                    <strong>{{ $partOrder->job->job_number }}</strong>
                                </a>
                                - {{ $partOrder->job->customer_name ?? 'No customer' }}
                            </div>
                        </div>
                        @endif


                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rq" class="form-label">RQ (Requisition) <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control {{ isset($partOrder) ? 'bg-light' : '' }} @error('rq') is-invalid @enderror" 
                                           id="rq" 
                                           name="rq" 
                                           value="{{ old('rq', $partOrder->rq ?? '') }}"
                                           {{ isset($partOrder) ? 'readonly' : 'required' }}>
                                    @error('rq')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="no_order_part" class="form-label">No. Order Part</label>
                                    <input type="text" 
                                           class="form-control @error('no_order_part') is-invalid @enderror" 
                                           id="no_order_part" 
                                           name="no_order_part" 
                                           value="{{ old('no_order_part', $partOrder->no_order_part ?? '') }}">
                                    @error('no_order_part')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="order_date" class="form-label">Order Date</label>
                                    <input type="date" 
                                           class="form-control bg-light" 
                                           id="order_date" 
                                           name="order_date" 
                                           value="{{ old('order_date', isset($partOrder) && $partOrder->order_date ? $partOrder->order_date->format('Y-m-d') : date('Y-m-d')) }}"
                                           readonly>
                                    <div class="form-text text-muted small">Auto-set when created</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="expected_date" class="form-label">Expected Date <span class="text-danger">*</span></label>
                                    <input type="date" 
                                           class="form-control date-shortcuts @error('expected_date') is-invalid @enderror" 
                                           id="expected_date" 
                                           name="expected_date" 
                                           value="{{ old('expected_date', isset($partOrder) && $partOrder->expected_date ? $partOrder->expected_date->format('Y-m-d') : '') }}"
                                           required>
                                    <div class="form-text text-muted small">
                                        <kbd>t</kbd> today | <kbd>→</kbd> +1d | <kbd>←</kbd> -1d | <kbd>↑</kbd> +7d | <kbd>↓</kbd> -7d
                                    </div>
                                    @error('expected_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status Dates</label>
                                    @if(isset($partOrder))
                                    <div class="small">
                                        <div class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="text-muted">Ready:</span>
                                            <span class="fw-semibold">{{ $partOrder->ready_date?->format('d/m/Y') ?? '-' }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1">
                                            <span class="text-muted">Received:</span>
                                            <span class="fw-semibold">{{ $partOrder->received_date?->format('d/m/Y') ?? '-' }}</span>
                                        </div>
                                    </div>
                                    <div class="form-text text-muted small">Auto-set on Kanban status change</div>
                                    @else
                                    <div class="form-control-plaintext text-muted small">-</div>
                                    @endif
                                </div>
                            </div>
                        </div>


                        @if(isset($partOrder))
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div>
                                <span class="badge" style="background-color: {{ $partOrder->status_color }}; font-size: 0.9rem;">
                                    {{ $partOrder->status_label }}
                                </span>
                                <span class="text-muted small ms-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Change status via <a href="{{ route('part-orders.kanban') }}" class="text-decoration-none">Kanban Board</a>
                                </span>
                            </div>
                        </div>
                        @else
                            <input type="hidden" name="status" value="pending">
                        @endif

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3">{{ old('notes', $partOrder->notes ?? '') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(isset($partOrder))
                        <div class="row mt-4 pt-3 border-top">
                            <div class="col-12">
                                <h5 class="mb-3 h6 fw-bold text-uppercase text-muted"><i class="bi bi-chat-dots me-2"></i>Activity & Comments</h5>
                                
                                <div class="card bg-light border-0 mb-3">
                                    <div class="card-body p-3" style="max-height: 400px; overflow-y: auto;">
                                        @php
                                            $rqRef = '[RQ:'.$partOrder->rq.']';
                                            $rqRemarks = $partOrder->job->remarks->filter(function($r) use ($rqRef) {
                                                return str_contains($r->remark_text, $rqRef);
                                            });
                                        @endphp

                                        @forelse($rqRemarks as $remark)
                                            <div class="d-flex mb-3">
                                                <div class="flex-shrink-0">
                                                    <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center text-primary fw-bold" 
                                                         style="width: 32px; height: 32px; font-size: 12px;">
                                                        {{ strtoupper(substr($remark->created_by ?? 'S', 0, 1)) }}
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong class="small text-dark">{{ $remark->created_by ?? 'System' }}</strong>
                                                        <span class="text-muted" style="font-size: 11px;">
                                                            {{ $remark->created_at->format('d M H:i') }}
                                                        </span>
                                                    </div>
                                                    <div class="p-2 bg-white rounded border-0 shadow-sm small text-dark">
                                                        {{ str_replace($rqRef, '', $remark->remark_text) }}
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-center text-muted small py-4">
                                                <i class="bi bi-chat-square-dots d-block mb-2 fs-4"></i>
                                                No comments for this RQ yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="new_comment" class="form-label small fw-bold">Add Comment</label>
                                    <textarea name="new_comment" id="new_comment" class="form-control" rows="2" placeholder="Type a comment..."></textarea>
                                    <div class="form-text small">This comment will be visible on the Job page as well.</div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                {{ isset($partOrder) ? 'Update' : 'Create' }} Part Order
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date keyboard shortcuts for fields with .date-shortcuts class
    document.querySelectorAll('.date-shortcuts').forEach(input => {
        input.addEventListener('keydown', function(e) {
            // Get current date or today if empty
            let currentDate = this.value ? new Date(this.value) : new Date();
            
            let handled = false;
            
            switch(e.key) {
                case 't':
                case 'T':
                    // t = Today
                    currentDate = new Date();
                    handled = true;
                    break;
                case 'ArrowRight':
                    // → = +1 day
                    currentDate.setDate(currentDate.getDate() + 1);
                    handled = true;
                    break;
                case 'ArrowLeft':
                    // ← = -1 day
                    currentDate.setDate(currentDate.getDate() - 1);
                    handled = true;
                    break;
                case 'ArrowUp':
                    // ↑ = +7 days (next week)
                    currentDate.setDate(currentDate.getDate() + 7);
                    handled = true;
                    break;
                case 'ArrowDown':
                    // ↓ = -7 days (previous week)
                    currentDate.setDate(currentDate.getDate() - 7);
                    handled = true;
                    break;
            }
            
            if (handled) {
                e.preventDefault();
                // Format as YYYY-MM-DD
                const year = currentDate.getFullYear();
                const month = String(currentDate.getMonth() + 1).padStart(2, '0');
                const day = String(currentDate.getDate()).padStart(2, '0');
                this.value = `${year}-${month}-${day}`;
            }
        });
    });
});
</script>
@endpush
