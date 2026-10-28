<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $statistics = [
            'products' => Product::count(),
            'categories' => Category::count(),
            'customers' => User::query()->where('is_admin', false)->count(),
            'orders' => Order::count(),
            'pendingOrders' => Order::query()->where('status', 'pending')->count(),
            'deliveredOrders' => Order::query()->where('status', 'delivered')->count(),
            'revenue' => Order::query()->where('status', 'delivered')->sum('grand_total'),
        ];
        $recentOrders = Order::query()
            ->with('user:id,name,email')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('statistics', 'recentOrders'));
    }
}
