<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_active_auctions', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->unsignedInteger('item_id')->nullable();
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('buyout_copper')->default(0);
            $table->unsignedBigInteger('bid_copper')->default(0);
            $table->unsignedInteger('time_left_seconds')->default(0);
            $table->unsignedInteger('num_bids')->default(0);
            $table->timestamp('synced_at');

            $table->index('character_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_active_auctions');
    }
};