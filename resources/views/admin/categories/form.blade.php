@extends('admin.layouts.app')

@section('title', $category->exists ? 'Edit Category' : 'New Category')

@section('content')
    <div class="card border-0 shadow-sm" style="max-width: 760px;">
        <div class="card-body p-4">
            <form action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST">
                @csrf
                @if ($category->exists) @method('PUT') @endif
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $category->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $category->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <input name="status" type="hidden" value="0">
                <div class="form-check mb-4">
                    <input class="form-check-input" id="status" name="status" type="checkbox" value="1" @checked(old('status', $category->exists ? $category->status : true))>
                    <label class="form-check-label" for="status">Active and visible to customers</label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" type="submit">{{ $category->exists ? 'Save changes' : 'Create category' }}</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
