<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\UpdateOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
        ]);
        $orders = Order::query()
            ->with('user:id,name,email')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('id', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status): mixed => $query->where('status', $status))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'filters'));
    }

    public function show(Order $order): View
    {
        $order->load(['user:id,name,email', 'items']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, UpdateOrderStatus $updateOrderStatus): RedirectResponse
    {
        $updateOrderStatus->handle($order, $request->string('status')->toString());

        return back()->with('success', 'Order status updated successfully.');
    }
}
