<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('character_key');
            $table->enum('mode', ['timed', 'freeform']);
            $table->enum('status', ['active', 'stopped'])->default('active');
            $table->unsignedInteger('planned_duration_minutes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['character_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_sessions');
    }
};