<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_character_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->enum('location', ['bag', 'bank']);
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('synced_at');

            $table->index(['character_key', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_character_inventory');
    }
};