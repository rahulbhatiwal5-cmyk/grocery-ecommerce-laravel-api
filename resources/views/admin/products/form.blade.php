@extends('admin.layouts.app')

@section('title', $product->exists ? 'Edit Product' : 'New Product')

@section('content')
    <div class="card border-0 shadow-sm" style="max-width: 900px;">
        <div class="card-body p-4">
            <form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST">
                @csrf
                @if ($product->exists) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Product name</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $product->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id" required>
                            <option value="">Choose a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="unit">Unit</label>
                        <input class="form-control @error('unit') is-invalid @enderror" id="unit" name="unit" placeholder="e.g. 1 kg" value="{{ old('unit', $product->unit) }}" required>
                        @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="price">Current price</label>
                        <input class="form-control @error('price') is-invalid @enderror" id="price" name="price" min="0" step="0.01" type="number" value="{{ old('price', $product->price) }}" required>
                        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="old_price">Previous price</label>
                        <input class="form-control @error('old_price') is-invalid @enderror" id="old_price" name="old_price" min="0" step="0.01" type="number" value="{{ old('old_price', $product->old_price) }}">
                        @error('old_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="stock">Stock</label>
                        <input class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" min="0" step="1" type="number" value="{{ old('stock', $product->stock ?? 0) }}" required>
                        @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="image_url">Image URL</label>
                        <input class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url" value="{{ old('image_url', $product->image_url) }}">
                        <div class="form-text">Existing image URLs are preserved for the customer application.</div>
                        @error('image_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <input name="status" type="hidden" value="0">
                <div class="form-check my-4">
                    <input class="form-check-input" id="status" name="status" type="checkbox" value="1" @checked(old('status', $product->exists ? $product->status : true))>
                    <label class="form-check-label" for="status">Active and available in the customer catalog</label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" type="submit">{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
