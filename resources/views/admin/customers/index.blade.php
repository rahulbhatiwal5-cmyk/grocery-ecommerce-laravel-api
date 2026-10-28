@extends('admin.layouts.app')

@section('title', 'Customers')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Customer</th><th>Joined</th><th>Orders</th><th>Delivered spend</th><th></th></tr></thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td><div class="fw-semibold">{{ $customer->name }}</div><small class="text-secondary">{{ $customer->email }}</small></td>
                            <td>{{ $customer->created_at->format('d M Y') }}</td>
                            <td>{{ $customer->orders_count }}</td>
                            <td>₹{{ number_format((float) ($customer->delivered_orders_sum_grand_total ?? 0), 2) }}</td>
                            <td><a class="btn btn-sm btn-outline-success" href="{{ route('admin.customers.show', $customer) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="5">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages()) <div class="card-body border-top">{{ $customers->links() }}</div> @endif
    </div>
@endsection
