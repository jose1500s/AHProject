<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_level_lookups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('bonus_signature'); // bonus_lists ordenados y unidos por coma, ej "10844,12774"
            $table->unsignedSmallInteger('raw_ilvl');    // lo que devuelve el BonusIdTool
            $table->unsignedSmallInteger('season_ilvl'); // ya ajustado con la tabla de temporada
            $table->timestamps();

            $table->unique(['item_id', 'bonus_signature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_level_lookups');
    }
};
