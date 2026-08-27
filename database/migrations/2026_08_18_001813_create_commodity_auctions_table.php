<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commodity_auctions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blizzard_auction_id')->unique();
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->string('time_left')->nullable();
            $table->timestamps();

            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_auctions');
    }
};