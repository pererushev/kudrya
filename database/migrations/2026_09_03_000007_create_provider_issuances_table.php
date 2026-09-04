<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 8);
            $table->string('idempotency_key')->unique();
            $table->string('status', 32);
            $table->string('code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_issuances');
    }
};
