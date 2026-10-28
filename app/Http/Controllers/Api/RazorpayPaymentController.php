<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRazorpayOrderRequest;
use App\Http\Requests\MarkRazorpayPaymentFailedRequest;
use App\Http\Requests\VerifyRazorpayPaymentRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use App\Services\RazorpayGateway;
use App\Services\RazorpayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayPaymentController extends Controller
{
    public function createOrder(CreateRazorpayOrderRequest $request, RazorpayPaymentService $payments): JsonResponse
    {
        $user = User::query()->findOrFail(auth('api')->id());
        $result = $payments->createOrder($user, (int) $request->integer('address_id'), $request->header('Idempotency-Key'));

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'razorpay_order_id' => $result['transaction']->provider_order_id,
                'amount' => $result['transaction']->amount,
                'currency' => $result['transaction']->currency,
                'key_id' => $result['key_id'],
                'prefill' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact' => $result['order']->shipping_phone,
                ],
            ],
        ], $result['created'] ? 201 : 200);
    }

    public function verify(VerifyRazorpayPaymentRequest $request, RazorpayPaymentService $payments): OrderResource
    {
        $data = $request->validated();

        try {
            $order = $payments->verifyClientPayment(
                User::query()->findOrFail(auth('api')->id()),
                (int) $data['order_id'],
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
                $data['razorpay_signature'],
            );
        } catch (SignatureVerificationError) {
            abort(422, 'Razorpay could not verify this payment signature.');
        }

        return new OrderResource($order);
    }

    public function fail(MarkRazorpayPaymentFailedRequest $request, RazorpayPaymentService $payments): OrderResource
    {
        $data = $request->validated();
        $order = $payments->markClientPaymentFailed(
            User::query()->findOrFail(auth('api')->id()),
            (int) $data['order_id'],
            $data['razorpay_order_id'],
            $data['error_code'] ?? null,
            $data['error_description'] ?? null,
        );

        return new OrderResource($order);
    }

    public function webhook(Request $request, RazorpayGateway $gateway, RazorpayPaymentService $payments): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature');

        if (! is_string($signature) || $signature === '') {
            abort(401, 'Missing Razorpay webhook signature.');
        }

        try {
            $gateway->verifyWebhookSignature($request->getContent(), $signature);
        } catch (SignatureVerificationError) {
            abort(401, 'Invalid Razorpay webhook signature.');
        }

        $payload = $request->json()->all();
        $event = $request->string('event')->toString();

        if (! in_array($event, ['payment.captured', 'payment.failed'], true)) {
            return response()->json(['received' => true]);
        }

        $payments->processWebhook($event, is_array($payload['payload'] ?? null) ? $payload['payload'] : []);

        return response()->json(['received' => true]);
    }
}
