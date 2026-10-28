<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shipping_full_name');
            $table->string('shipping_phone', 30);
            $table->string('shipping_address_line_1');
            $table->string('shipping_address_line_2')->nullable();
            $table->string('shipping_city', 100);
            $table->string('shipping_state', 100);
            $table->string('shipping_postal_code', 20);
            $table->string('shipping_country', 100);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_charge', 10, 2);
            $table->decimal('grand_total', 10, 2);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->default('cod');
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->uuid('idempotency_key')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'placed_at']);
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
