<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name');
            $table->string('sku', 100)->nullable()->index();
            $table->string('category', 100)->default('General')->index();
            $table->string('brand', 100)->nullable();
            $table->string('unit', 50)->default('pcs');
            $table->integer('stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('selling_price', 10, 2)->default(0.00);
            $table->integer('min_stock_alert')->default(5);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_group_product')->default(false);
            $table->longText('components_json')->nullable();
            $table->longText('image_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
