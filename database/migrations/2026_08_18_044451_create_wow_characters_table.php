<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_characters', function (Blueprint $table) {
            $table->id();
            $table->string('character_key')->unique();
            $table->string('name');
            $table->string('realm');
            $table->string('class')->nullable();
            $table->unsignedTinyInteger('level')->default(0);
            $table->unsignedSmallInteger('ilvl')->default(0);
            $table->unsignedBigInteger('gold_copper')->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_characters');
    }
};