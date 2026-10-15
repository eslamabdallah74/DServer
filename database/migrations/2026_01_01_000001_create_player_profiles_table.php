<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->string('player_id')->primary();
            $table->string('nickname')->default('Player');
            $table->integer('coins')->default(500);
            $table->text('owned_roles_json')->nullable();
            $table->integer('stats_wins')->default(0);
            $table->integer('stats_losses')->default(0);
            $table->integer('stats_matches')->default(0);
            $table->boolean('is_banned')->default(false);
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_profiles');
    }
};
