<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->uuid('order_id');
            $table->unsignedInteger('amount_cents');
            $table->string('status', 32);
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
