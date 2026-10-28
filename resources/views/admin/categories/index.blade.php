@extends('admin.layouts.app')

@section('title', 'Categories')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-secondary mb-0">Organize the store catalog.</p>
        <a class="btn btn-success" href="{{ route('admin.categories.create') }}">Add category</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Slug</th><th>Products</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td><div class="fw-semibold">{{ $category->name }}</div><small class="text-secondary">{{ $category->description }}</small></td>
                            <td><code>{{ $category->slug }}</code></td>
                            <td>{{ $category->products_count }}</td>
                            <td><span class="badge {{ $category->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $category->status ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $category->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-success" href="{{ route('admin.categories.edit', $category) }}">Edit</a>
                                <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete this category?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="6">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($categories->hasPages()) <div class="card-body border-top">{{ $categories->links() }}</div> @endif
    </div>
@endsection
