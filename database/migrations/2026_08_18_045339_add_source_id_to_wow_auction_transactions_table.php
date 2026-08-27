<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wow_auction_transactions', function (Blueprint $table) {
            $table->unsignedInteger('source_id')->nullable()->after('character_key');
            $table->unique(['character_key', 'source_id']);
        });

        Schema::table('wow_post_fees', function (Blueprint $table) {
            $table->unique(['character_key', 'item_name', 'fee_copper', 'occurred_at'], 'wow_post_fees_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wow_auction_transactions', function (Blueprint $table) {
            $table->dropUnique(['character_key', 'source_id']);
            $table->dropColumn('source_id');
        });

        Schema::table('wow_post_fees', function (Blueprint $table) {
            $table->dropUnique('wow_post_fees_dedupe_unique');
        });
    }
};