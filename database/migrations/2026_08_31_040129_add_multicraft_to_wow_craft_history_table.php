<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_craft_history', function (Blueprint $table) {
            $table->unsignedInteger('multicraft')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('wow_craft_history', function (Blueprint $table) {
            $table->dropColumn('multicraft');
        });
    }
};