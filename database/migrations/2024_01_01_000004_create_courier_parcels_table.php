<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_parcels', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('consignment_id', 100)->unique();
            $table->string('tracking_code', 100)->nullable();
            $table->string('order_id', 64)->nullable();
            $table->string('order_number', 50)->nullable();
            $table->string('courier_partner', 50)->default('Pathao');
            $table->string('recipient_name', 150);
            $table->string('recipient_phone', 30);
            $table->text('recipient_address');
            $table->string('recipient_city', 100)->default('Dhaka');
            $table->string('recipient_zone', 100)->nullable();
            $table->string('recipient_area', 100)->nullable();
            $table->string('item_type', 50)->default('parcel');
            $table->decimal('weight_kg', 5, 2)->default(0.50);
            $table->integer('number_of_items')->default(1);
            $table->decimal('cod_amount', 10, 2)->default(0.00);
            $table->decimal('delivery_fee', 10, 2)->default(60.00);
            $table->string('delivery_type', 50)->default('48');
            $table->text('special_instructions')->nullable();
            $table->string('status', 50)->default('pending_pickup');
            $table->timestamp('dispatched_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_parcels');
    }
};
