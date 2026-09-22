<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_gold_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('characters_gold_copper');
            $table->unsignedBigInteger('warband_gold_copper');
            $table->unsignedBigInteger('total_gold_copper');
            $table->timestamp('snapshot_at');
            $table->index('snapshot_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_gold_snapshots');
    }
};