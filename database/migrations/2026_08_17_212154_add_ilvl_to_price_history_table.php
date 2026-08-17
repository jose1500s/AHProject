<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('price_history')->truncate();

        Schema::table('price_history', function (Blueprint $table) {
            $table->unsignedSmallInteger('ilvl')->nullable()->after('item_id');
            $table->index(['connected_realm_id', 'item_id', 'ilvl', 'snapshot_at'], 'price_history_realm_item_ilvl_snap_idx');
        });
    }

    public function down(): void
    {
        Schema::table('price_history', function (Blueprint $table) {
            $table->dropIndex('price_history_realm_item_ilvl_snap_idx');
            $table->dropColumn('ilvl');
        });
    }
};