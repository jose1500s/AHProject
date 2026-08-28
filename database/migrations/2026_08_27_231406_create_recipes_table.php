<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('blizzard_recipe_id')->unique();
            $table->foreignId('profession_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('produces_item_id')->nullable();
            $table->unsignedInteger('produces_quantity')->default(1);
            $table->unsignedTinyInteger('rank')->nullable();
            $table->json('bonus_ids')->nullable();
            $table->string('icon_url')->nullable();
            $table->timestamps();

            $table->index('produces_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};