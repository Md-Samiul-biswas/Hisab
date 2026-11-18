<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'sku', 'category', 'brand', 'unit',
        'stock', 'reserved_stock', 'unit_cost', 'selling_price',
        'min_stock_alert', 'is_active', 'is_group_product',
        'components_json', 'image_url', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_group_product' => 'boolean',
        'components_json' => 'array',
        'unit_cost' => 'float',
        'selling_price' => 'float',
        'stock' => 'integer',
    ];
}
