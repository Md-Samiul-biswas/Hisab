<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_collections', function (Blueprint $table) {
            $table->string('collection_name', 64);
            $table->string('doc_id', 128);
            $table->longText('data_json');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['collection_name', 'doc_id']);
            $table->index('collection_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_collections');
    }
};
