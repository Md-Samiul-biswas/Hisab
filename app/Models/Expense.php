<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expenses';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'expense_number', 'title', 'category', 'expense_type',
        'product_id', 'product_name', 'quantity', 'unit_cost',
        'amount', 'date', 'payment_account_id', 'payment_account_name',
        'payment_method', 'vendor', 'notes',
    ];
}
