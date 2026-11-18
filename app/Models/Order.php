<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'order_number', 'customer_id', 'customer_name',
        'customer_phone', 'customer_address', 'items_json',
        'packaging_items_json', 'subtotal', 'product_cost_total',
        'packaging_cost', 'delivery_charge', 'discount',
        'total_amount', 'paid_amount', 'due_amount',
        'status', 'payment_status', 'courier_partner',
        'consignment_id', 'tracking_code', 'order_date', 'notes',
    ];

    protected $casts = [
        'items_json' => 'array',
        'packaging_items_json' => 'array',
        'order_date' => 'date',
        'subtotal' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'due_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function parcel()
    {
        return $this->hasOne(CourierParcel::class, 'order_id');
    }
}
