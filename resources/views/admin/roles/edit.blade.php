@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-pencil me-2"></i>Edit Role: {{ $role->name }}</h1>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <form action="{{ route('admin.roles.update', $role) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label for="name" class="form-label">Role Name</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $role->name) }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Slug</label>
                <input type="text" class="form-control" value="{{ $role->slug }}" disabled>
                <div class="form-text">Slug cannot be changed</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" name="description" id="description" rows="2">{{ old('description', $role->description) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
                <a href="{{ route('admin.roles.permissions', $role) }}" class="btn btn-outline-primary"><i class="bi bi-key me-1"></i>Permissions</a>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
