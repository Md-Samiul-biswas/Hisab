<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'phone', 'address', 'district',
        'total_orders', 'total_purchases', 'amount_paid',
        'outstanding_due', 'notes',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
