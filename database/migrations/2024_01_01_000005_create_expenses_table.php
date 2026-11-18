<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('expense_number', 50);
            $table->string('title');
            $table->string('category', 100);
            $table->string('expense_type', 30)->default('operating');
            $table->string('product_id', 64)->nullable();
            $table->string('product_name')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->date('date');
            $table->string('payment_account_id', 64)->nullable();
            $table->string('payment_account_name', 150)->nullable();
            $table->string('payment_method', 50)->default('Cash');
            $table->string('vendor', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
