<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use Razorpay\Api\Api;

class RazorpayGateway
{
    /**
     * @return array<string, mixed>
     */
    public function createOrder(int $amount, string $receipt, array $notes = []): array
    {
        return $this->api()->order->create([
            'amount' => $amount,
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => $notes,
        ])->toArray();
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): void
    {
        $this->api()->utility->verifyPaymentSignature([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);
    }

    public function verifyWebhookSignature(string $payload, string $signature): void
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '') {
            throw new HttpResponseException(response()->json(['message' => 'Webhook configuration is unavailable.'], 503));
        }

        $this->api()->utility->verifyWebhookSignature($payload, $signature, $secret);
    }

    public function publicKey(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    private function api(): Api
    {
        $keyId = $this->publicKey();
        $keySecret = (string) config('services.razorpay.key_secret');

        if ($keyId === '' || $keySecret === '') {
            throw new HttpResponseException(response()->json(['message' => 'Online payments are currently unavailable.'], 503));
        }

        return new Api($keyId, $keySecret);
    }
}
