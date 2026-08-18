<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commodity_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->unsignedBigInteger('min_price_copper');
            $table->unsignedInteger('listings');
            $table->unsignedInteger('volume');
            $table->timestamp('snapshot_at');

            $table->index(['item_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_price_history');
    }
};