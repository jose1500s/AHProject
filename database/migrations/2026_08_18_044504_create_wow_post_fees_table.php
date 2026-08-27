<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_post_fees', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->string('item_name');
            $table->unsignedBigInteger('fee_copper')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('character_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_post_fees');
    }
};