<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name', 150);
            $table->string('phone', 30)->index();
            $table->text('address')->nullable();
            $table->string('district', 100)->default('Dhaka');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_purchases', 12, 2)->default(0.00);
            $table->decimal('amount_paid', 12, 2)->default(0.00);
            $table->decimal('outstanding_due', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
