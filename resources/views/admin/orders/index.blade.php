@extends('admin.layouts.app')

@section('title', 'Orders')

@section('content')
    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.orders.index') }}">
        <div class="col-sm-5 col-md-4"><input class="form-control" name="search" placeholder="Order ID, customer, or email" value="{{ $filters['search'] ?? '' }}"></div>
        <div class="col-sm-4 col-md-3">
            <select class="form-select" name="status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Order::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-success" type="submit">Filter</button></div>
    </form>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="fw-semibold">#{{ $order->id }}</td>
                            <td><div>{{ $order->user->name }}</div><small class="text-secondary">{{ $order->user->email }}</small></td>
                            <td>₹{{ number_format((float) $order->grand_total, 2) }}</td>
                            <td><div class="text-uppercase">{{ $order->payment_method }}</div><small class="text-capitalize text-secondary">{{ $order->payment_status }}</small></td>
                            <td><span class="badge text-bg-light border text-capitalize">{{ str_replace('_', ' ', $order->status) }}</span></td>
                            <td>{{ $order->placed_at->format('d M Y, h:i A') }}</td>
                            <td><a class="btn btn-sm btn-outline-success" href="{{ route('admin.orders.show', $order) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="7">No orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages()) <div class="card-body border-top">{{ $orders->links() }}</div> @endif
    </div>
@endsection
