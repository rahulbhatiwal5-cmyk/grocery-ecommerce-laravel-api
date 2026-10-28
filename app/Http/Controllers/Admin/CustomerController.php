<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = User::query()
            ->where('is_admin', false)
            ->withCount('orders')
            ->withSum([
                'orders as delivered_orders_sum_grand_total' => fn ($query) => $query->where('status', 'delivered'),
            ], 'grand_total')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $user): View
    {
        $customer = User::query()
            ->whereKey($user->id)
            ->where('is_admin', false)
            ->with([
                'addresses' => fn ($query) => $query->orderByDesc('is_default')->latest(),
                'orders' => fn ($query) => $query->orderByDesc('placed_at')->orderByDesc('id')->limit(10),
            ])
            ->withCount('orders')
            ->withSum([
                'orders as delivered_orders_sum_grand_total' => fn ($query) => $query->where('status', 'delivered'),
            ], 'grand_total')
            ->firstOrFail();

        return view('admin.customers.show', compact('customer'));
    }
}
