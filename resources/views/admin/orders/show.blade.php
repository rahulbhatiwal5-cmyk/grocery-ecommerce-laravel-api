@extends('admin.layouts.app')

@section('title', 'Order #'.$order->id)

@section('content')
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.index') }}">← All orders</a>
        <span class="text-secondary">Placed {{ $order->placed_at->format('d M Y, h:i A') }}</span>
    </div>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Order #{{ $order->id }}</h2></div>
                <div class="table-responsive">
                    <table class="table mb-0"><thead class="table-light"><tr><th>Product</th><th>Unit</th><th>Quantity</th><th>Unit price</th><th class="text-end">Line total</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr><td class="fw-semibold">{{ $item->product_name }}</td><td>{{ $item->unit }}</td><td>{{ $item->quantity }}</td><td>₹{{ number_format((float) $item->unit_price, 2) }}</td><td class="text-end">₹{{ number_format((float) $item->line_total, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body ms-auto" style="min-width: 280px;">
                    <div class="d-flex justify-content-between"><span class="text-secondary">Subtotal</span><span>₹{{ number_format((float) $order->subtotal, 2) }}</span></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-secondary">Delivery</span><span>₹{{ number_format((float) $order->delivery_charge, 2) }}</span></div>
                    <hr><div class="d-flex justify-content-between fw-bold"><span>Grand total</span><span>₹{{ number_format((float) $order->grand_total, 2) }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6">Customer</h2><div class="fw-semibold">{{ $order->user->name }}</div><div class="text-secondary">{{ $order->user->email }}</div>
            </div></div>
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6">Shipping address</h2>
                <div class="fw-semibold">{{ $order->shipping_full_name }}</div><div>{{ $order->shipping_address_line_1 }}</div>
                @if ($order->shipping_address_line_2)<div>{{ $order->shipping_address_line_2 }}</div>@endif
                <div>{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postal_code }}</div><div>{{ $order->shipping_country }}</div><div class="mt-2 text-secondary">{{ $order->shipping_phone }}</div>
            </div></div>
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6">Status and payment</h2>
                <p class="mb-2"><span class="text-secondary">Payment:</span> <span class="text-capitalize">{{ $order->payment_method }} · {{ $order->payment_status }}</span></p>
                @if ($order->paymentTransaction?->provider_payment_id)
                    <p class="mb-2 small"><span class="text-secondary">Razorpay payment ID:</span> {{ $order->paymentTransaction->provider_payment_id }}</p>
                @endif
                <form action="{{ route('admin.orders.status.update', $order) }}" method="POST">
                    @csrf @method('PATCH')
                    <label class="form-label" for="status">Order status</label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                        @foreach (\App\Models\Order::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <button class="btn btn-success w-100 mt-3" type="submit">Update status</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection
