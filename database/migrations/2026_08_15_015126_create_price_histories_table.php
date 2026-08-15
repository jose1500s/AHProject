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
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connected_realm_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('min_price_copper')->nullable();
            $table->unsignedInteger('listings');
            $table->unsignedBigInteger('volume');
            $table->timestamp('snapshot_at');

            $table->index(['connected_realm_id', 'item_id', 'snapshot_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
