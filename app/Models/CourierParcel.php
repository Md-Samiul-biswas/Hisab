<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierParcel extends Model
{
    protected $table = 'courier_parcels';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'consignment_id', 'tracking_code', 'order_id',
        'order_number', 'courier_partner', 'recipient_name',
        'recipient_phone', 'recipient_address', 'recipient_city',
        'recipient_zone', 'recipient_area', 'item_type',
        'weight_kg', 'number_of_items', 'cod_amount',
        'delivery_fee', 'delivery_type', 'special_instructions',
        'status', 'dispatched_at',
    ];
}
