@extends('admin.layouts.app')

@section('title', 'Customer Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3"><a class="btn btn-outline-secondary" href="{{ route('admin.customers.index') }}">← All customers</a><span class="text-secondary">Joined {{ $customer->created_at->format('d M Y') }}</span></div>
    <div class="row g-4">
        <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h5">{{ $customer->name }}</h2><p class="text-secondary">{{ $customer->email }}</p><hr>
            <div class="d-flex justify-content-between"><span>Orders</span><strong>{{ $customer->orders_count }}</strong></div>
            <div class="d-flex justify-content-between mt-2"><span>Delivered spend</span><strong>₹{{ number_format((float) ($customer->delivered_orders_sum_grand_total ?? 0), 2) }}</strong></div>
        </div></div></div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h6 mb-0">Saved addresses</h2></div><div class="card-body">
                @forelse ($customer->addresses as $address)
                    <div class="border rounded p-3 mb-2"><div class="fw-semibold">{{ $address->full_name }} @if($address->is_default)<span class="badge text-bg-success ms-1">Default</span>@endif</div><div>{{ $address->address_line_1 }} @if($address->address_line_2), {{ $address->address_line_2 }}@endif</div><div>{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</div><small class="text-secondary">{{ $address->phone }}</small></div>
                @empty <p class="text-secondary mb-0">No saved addresses.</p>
                @endforelse
            </div></div>
            <div class="card border-0 shadow-sm"><div class="card-header bg-white"><h2 class="h6 mb-0">Recent orders</h2></div><div class="table-responsive"><table class="table mb-0"><thead class="table-light"><tr><th>Order</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
                @forelse ($customer->orders as $order)
                    <tr><td>#{{ $order->id }}<small class="d-block text-secondary">{{ $order->placed_at->format('d M Y') }}</small></td><td class="text-capitalize">{{ str_replace('_', ' ', $order->status) }}</td><td>₹{{ number_format((float) $order->grand_total, 2) }}</td><td><a class="btn btn-sm btn-outline-success" href="{{ route('admin.orders.show', $order) }}">View</a></td></tr>
                @empty <tr><td class="text-center text-secondary py-3" colspan="4">No orders yet.</td></tr>
                @endforelse
            </tbody></table></div></div>
        </div>
    </div>
@endsection
