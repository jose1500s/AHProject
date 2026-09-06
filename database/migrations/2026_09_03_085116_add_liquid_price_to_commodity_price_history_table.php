<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commodity_price_history', function (Blueprint $table) {
            $table->unsignedBigInteger('liquid_price_copper')->nullable()->after('min_price_copper');
        });
    }

    public function down(): void
    {
        Schema::table('commodity_price_history', function (Blueprint $table) {
            $table->dropColumn('liquid_price_copper');
        });
    }
};