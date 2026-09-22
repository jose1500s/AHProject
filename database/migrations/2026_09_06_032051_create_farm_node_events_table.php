<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_node_events', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->enum('profession', ['herbalism', 'mining']);
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Único por personaje + instante + ítem: evita duplicados en un resync.
            // Sigue permitiendo varias filas por nodo si suelta más de un tipo de
            // ítem, porque cada fila tiene un item_id distinto.
            $table->unique(['character_key', 'occurred_at', 'item_id']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_node_events');
    }
};