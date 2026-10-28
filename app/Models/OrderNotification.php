<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends Model
{
    /** @use HasFactory<\Database\Factories\OrderNotificationFactory> */
    use HasFactory;

    protected $fillable = ['type', 'channel', 'status', 'sent_at', 'failed_at', 'error_message'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
