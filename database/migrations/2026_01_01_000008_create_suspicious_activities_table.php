<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspicious_activities', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 10)->index();
            $table->string('player_a_id', 64)->index();
            $table->string('player_b_id', 64)->nullable()->index();
            $table->string('reason', 100); // e.g. SAME_IP_OPPOSING_TEAMS, CHAT_TOXICITY, ROLE_LEAK
            $table->string('severity', 20)->default('warning'); // warning, critical
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['pending', 'dismissed', 'actioned'])->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspicious_activities');
    }
};

