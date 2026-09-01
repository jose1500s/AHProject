<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wow_character_vault', function (Blueprint $table) {
            $table->unsignedInteger('ilvl')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('wow_character_vault', function (Blueprint $table) {
            $table->dropColumn('ilvl');
        });
    }
};