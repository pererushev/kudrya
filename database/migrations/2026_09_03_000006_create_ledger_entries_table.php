<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->string('account', 64);
            $table->integer('amount_cents');
            $table->string('reason', 32);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->index(['order_id', 'reason']);
            $table->index('account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
