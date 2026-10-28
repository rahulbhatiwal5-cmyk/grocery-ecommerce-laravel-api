<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'out_for_delivery',
        'delivered',
        'cancelled',
    ];

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'shipping_full_name',
        'shipping_phone',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'subtotal',
        'delivery_charge',
        'grand_total',
        'status',
        'payment_status',
        'payment_method',
        'notes',
        'placed_at',
        'idempotency_key',
        'request_hash',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
