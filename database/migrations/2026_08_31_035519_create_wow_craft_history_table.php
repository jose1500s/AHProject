<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_craft_history', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('concentration_spent')->default(0);
            $table->unsignedInteger('concentration_currency_id')->nullable();
            $table->unsignedTinyInteger('crafting_quality')->nullable();
            $table->boolean('is_crit')->default(false);
            $table->boolean('first_craft_reward')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['character_key', 'source_id']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_craft_history');
    }
};