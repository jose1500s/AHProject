<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('farm_node_events', function (Blueprint $table) {
            $table->dropUnique(['character_key', 'occurred_at', 'item_id']);
            $table->unsignedBigInteger('source_id')->nullable()->after('character_key');
            $table->unique(['character_key', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('farm_node_events', function (Blueprint $table) {
            $table->dropUnique(['character_key', 'source_id']);
            $table->dropColumn('source_id');
            $table->unique(['character_key', 'occurred_at', 'item_id']);
        });
    }
};