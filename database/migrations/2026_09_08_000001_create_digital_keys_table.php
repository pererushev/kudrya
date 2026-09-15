<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_keys', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->uuid('order_id')->nullable();
            $table->string('request_id')->nullable()->unique();
            $table->string('provider', 8)->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index('allocated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_keys');
    }
};
