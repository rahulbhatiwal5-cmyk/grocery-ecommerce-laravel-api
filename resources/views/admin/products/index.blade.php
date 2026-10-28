@extends('admin.layouts.app')

@section('title', 'Products')

@section('content')
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
        <form class="row g-2" method="GET" action="{{ route('admin.products.index') }}">
            <div class="col-auto"><input class="form-control" name="search" placeholder="Search products" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-auto">
                <select class="form-select" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-success" type="submit">Filter</button></div>
        </form>
        <a class="btn btn-success" href="{{ route('admin.products.create') }}">Add product</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        @php($stockLabel = $product->stock === 0 ? 'Out of Stock' : ($product->stock <= 10 ? 'Low Stock' : 'In Stock'))
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($product->image_url)<img class="product-thumb" src="{{ $product->image_url }}" alt="">@else <div class="product-thumb d-flex align-items-center justify-content-center text-secondary">—</div>@endif
                                    <div><div class="fw-semibold">{{ $product->name }}</div><small class="text-secondary">{{ $product->unit }}</small></div>
                                </div>
                            </td>
                            <td>{{ $product->category->name }}</td>
                            <td><div>₹{{ number_format((float) $product->price, 2) }}</div>@if ($product->old_price)<small class="text-secondary text-decoration-line-through">₹{{ number_format((float) $product->old_price, 2) }}</small>@endif</td>
                            <td><span class="badge {{ $product->stock === 0 ? 'text-bg-danger' : ($product->stock <= 10 ? 'text-bg-warning' : 'text-bg-success') }}">{{ $stockLabel }}: {{ $product->stock }}</span></td>
                            <td><span class="badge {{ $product->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $product->status ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $product->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-success" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete this product?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="7">No products match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages()) <div class="card-body border-top">{{ $products->links() }}</div> @endif
    </div>
@endsection
