<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id')->unique();
            $table->string('provider', 8)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('status', 32);
            $table->text('code')->nullable();
            $table->string('provider_ref')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
