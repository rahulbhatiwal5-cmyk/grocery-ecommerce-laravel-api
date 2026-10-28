@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        @foreach ([
            ['Products', $statistics['products'], 'primary'],
            ['Categories', $statistics['categories'], 'secondary'],
            ['Customers', $statistics['customers'], 'info'],
            ['Orders', $statistics['orders'], 'dark'],
            ['Pending orders', $statistics['pendingOrders'], 'warning'],
            ['Delivered orders', $statistics['deliveredOrders'], 'success'],
        ] as [$label, $value, $color])
            <div class="col-sm-6 col-xl-4">
                <div class="card metric-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small">{{ $label }}</div>
                        <div class="display-6 fw-semibold text-{{ $color }}">{{ number_format($value) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-sm-6 col-xl-4">
            <div class="card metric-card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Delivered revenue</div>
                    <div class="display-6 fw-semibold text-success">₹{{ number_format((float) $statistics['revenue'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Recent Orders</h2>
            <a class="btn btn-sm btn-outline-success" href="{{ route('admin.orders.index') }}">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td class="fw-semibold">#{{ $order->id }}</td>
                            <td><div>{{ $order->user->name }}</div><small class="text-secondary">{{ $order->user->email }}</small></td>
                            <td>₹{{ number_format((float) $order->grand_total, 2) }}</td>
                            <td><span class="badge text-bg-light border text-capitalize">{{ str_replace('_', ' ', $order->status) }}</span></td>
                            <td class="text-capitalize">{{ $order->payment_status }}</td>
                            <td>{{ $order->placed_at->format('d M Y, h:i A') }}</td>
                            <td><a class="btn btn-sm btn-outline-success" href="{{ route('admin.orders.show', $order) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="7">No orders have been placed yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
