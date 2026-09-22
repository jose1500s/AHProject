<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_character_gold_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->unsignedBigInteger('gold_copper');
            $table->timestamp('snapshot_at');
            $table->index(['character_key', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_character_gold_snapshots');
    }
};