<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_logs', function (Blueprint $table) {
            $table->id('match_id');
            $table->string('room_code', 10)->index();
            $table->string('winning_faction', 50);
            $table->json('players_json')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_logs');
    }
};
