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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connected_realm_id');
            $table->unsignedBigInteger('blizzard_auction_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('buyout')->nullable();
            $table->unsignedBigInteger('bid')->nullable();
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('time_left')->nullable();
            $table->timestamps();

            $table->unique(['connected_realm_id', 'blizzard_auction_id']);
            $table->index(['connected_realm_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
