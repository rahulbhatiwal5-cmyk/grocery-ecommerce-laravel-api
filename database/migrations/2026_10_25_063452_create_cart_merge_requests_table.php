<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_merge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('payload_hash', 64);
            $table->timestamps();
            $table->unique(['cart_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_merge_requests');
    }
};
