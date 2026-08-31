<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_character_vault', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->string('category');
            $table->unsignedTinyInteger('slot_index');
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('progress');
            $table->boolean('unlocked');
            $table->unsignedInteger('level')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['character_key', 'category', 'slot_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_character_vault');
    }
};