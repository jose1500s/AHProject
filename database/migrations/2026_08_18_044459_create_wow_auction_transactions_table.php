<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_auction_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->enum('type', ['sale', 'purchase']);
            $table->string('item_name');
            $table->unsignedInteger('item_id')->nullable();
            $table->string('counterparty')->nullable();
            $table->unsignedBigInteger('sale_price_copper')->default(0);
            $table->unsignedBigInteger('deposit_copper')->default(0);
            $table->unsignedBigInteger('consignment_copper')->default(0);
            $table->bigInteger('amount_copper')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['character_key', 'type']);
            $table->index('item_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_auction_transactions');
    }
};