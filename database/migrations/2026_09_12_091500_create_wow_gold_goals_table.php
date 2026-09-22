<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wow_gold_goals', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Mi meta de oro');
            $table->unsignedBigInteger('target_gold_copper');
            $table->date('deadline_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_gold_goals');
    }
};