<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('order_number', 50)->unique();
            $table->string('customer_id', 64)->nullable();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30);
            $table->text('customer_address');
            $table->longText('items_json');
            $table->longText('packaging_items_json')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('product_cost_total', 12, 2)->default(0.00);
            $table->decimal('packaging_cost', 10, 2)->default(0.00);
            $table->decimal('delivery_charge', 10, 2)->default(60.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('due_amount', 12, 2)->default(0.00);
            $table->string('status', 50)->default('pending');
            $table->string('payment_status', 50)->default('unpaid');
            $table->string('courier_partner', 50)->default('Pathao');
            $table->string('consignment_id', 100)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->date('order_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
