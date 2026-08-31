<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_character_concentration', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->string('profession');
            $table->unsignedInteger('currency_id');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('max_quantity');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['character_key', 'profession']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_character_concentration');
    }
};