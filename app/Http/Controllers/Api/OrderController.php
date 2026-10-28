<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', auth('api')->id())
            ->with('paymentTransaction')
            ->withSum('items', 'quantity')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->get();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request, OrderCheckoutService $checkout): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->findOrFail(auth('api')->id());
        $result = $checkout->createCodOrder($user, (int) $data['address_id'], $request->header('Idempotency-Key'));

        return (new OrderResource($result['order']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(string $order): OrderResource
    {
        $order = Order::query()
            ->whereKey($order)
            ->where('user_id', auth('api')->id())
            ->with(['items', 'paymentTransaction'])
            ->withSum('items', 'quantity')
            ->firstOrFail();

        return new OrderResource($order);
    }
}
