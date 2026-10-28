<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · GreenBasket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --gb-green: #198754; --gb-dark: #173b2d; }
        body { background: #f5f7f6; }
        .admin-shell { min-height: 100vh; }
        .admin-sidebar { background: var(--gb-dark); min-height: 100vh; width: 250px; }
        .admin-sidebar .nav-link { color: rgba(255,255,255,.76); border-radius: .5rem; }
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { background: rgba(255,255,255,.14); color: #fff; }
        .admin-main { min-width: 0; }
        .metric-card { border: 0; border-radius: 1rem; }
        .table > :not(caption) > * > * { vertical-align: middle; }
        .product-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: .5rem; background: #e9ecef; }
        @media (max-width: 767.98px) { .admin-sidebar { min-height: auto; width: 100%; } }
    </style>
</head>
<body>
<div class="admin-shell d-md-flex">
    <aside class="admin-sidebar p-3">
        <a class="d-flex align-items-center gap-2 text-white text-decoration-none fs-4 fw-bold mb-4" href="{{ route('admin.dashboard') }}">
            <span class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">G</span>
            GreenBasket
        </a>
        <div class="small text-uppercase text-white-50 fw-semibold mb-2">Administration</div>
        <nav class="nav nav-pills flex-column gap-1">
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">Categories</a>
            <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
            <a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">Orders</a>
            <a class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}">Customers</a>
        </nav>
        <form action="{{ route('admin.logout') }}" method="POST" class="mt-4">
            @csrf
            <button class="btn btn-outline-light w-100" type="submit">Logout</button>
        </form>
    </aside>
    <main class="admin-main flex-grow-1">
        <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <div>
                <div class="text-secondary small">GreenBasket Admin</div>
                <h1 class="h4 mb-0">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="text-end">
                <div class="fw-semibold">{{ auth('web')->user()->name }}</div>
                <small class="text-secondary">Administrator</small>
            </div>
        </header>
        <div class="p-4">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Please correct the highlighted fields.</strong>
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
