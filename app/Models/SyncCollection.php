<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncCollection extends Model
{
    protected $table = 'sync_collections';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'collection_name',
        'doc_id',
        'data_json',
    ];
}
