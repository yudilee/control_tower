@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-bell me-2"></i>Notifications</h1>
        <p class="text-muted">View and manage your notifications</p>
    </div>
    <div class="d-flex gap-2">
        @if($notifications->where('read_at', null)->count() > 0)
        <form action="{{ route('notifications.mark-all-read') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-check-all me-1"></i>Mark All Read
            </button>
        </form>
        @endif
        @if($notifications->count() > 0)
        <form action="{{ route('notifications.clear-all') }}" method="POST" onsubmit="return confirm('Clear all notifications?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-trash me-1"></i>Clear All
            </button>
        </form>
        @endif
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3 class="mb-0">{{ $notifications->count() }}</h3>
                <small class="opacity-75">Total Notifications</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3 class="mb-0">{{ $unreadCount }}</h3>
                <small class="opacity-75">Unread</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3 class="mb-0">{{ $notifications->count() - $unreadCount }}</h3>
                <small class="opacity-75">Read</small>
            </div>
        </div>
    </div>
</div>

<!-- Notifications List -->
<div class="card">
    <div class="card-body p-0">
        @forelse($notifications as $notification)
        <div class="d-flex align-items-start p-3 border-bottom {{ !$notification->isRead() ? 'bg-light' : '' }}">
            <div class="me-3">
                <span class="badge bg-{{ $notification->color }} rounded-circle p-2">
                    <i class="bi bi-{{ $notification->icon }} fs-5"></i>
                </span>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">
                            {{ $notification->title }}
                            @if(!$notification->isRead())
                            <span class="badge bg-primary ms-2">New</span>
                            @endif
                        </h6>
                        <p class="mb-1 text-muted">{{ $notification->message }}</p>
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}
                            • {{ $notification->created_at->format('d M Y, H:i') }}
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        @if($notification->link)
                        <form action="{{ route('notifications.read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>
                        @elseif(!$notification->isRead())
                        <form action="{{ route('notifications.read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as read">
                                <i class="bi bi-check"></i>
                            </button>
                        </form>
                        @endif
                        <form action="{{ route('notifications.destroy', $notification) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bell-slash display-1 opacity-25 d-block mb-3"></i>
            <h5>No Notifications</h5>
            <p class="mb-0">You're all caught up!</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
