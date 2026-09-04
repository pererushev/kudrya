<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('title');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_rank')->default(0);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('stock_qty')->default(0);
            $table->boolean('is_available')->default(false);
            $table->timestamps();
        });

        DB::statement('
            CREATE INDEX products_storefront_idx
            ON products (category_id, sort_rank DESC, id)
            INCLUDE (sku, title, price_cents, stock_qty)
            WHERE is_available
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
