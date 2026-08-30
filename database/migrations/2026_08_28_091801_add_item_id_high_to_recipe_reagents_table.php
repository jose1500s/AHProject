<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_reagents', function (Blueprint $table) {
            $table->unsignedInteger('item_id_high')->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_reagents', function (Blueprint $table) {
            $table->dropColumn('item_id_high');
        });
    }
};