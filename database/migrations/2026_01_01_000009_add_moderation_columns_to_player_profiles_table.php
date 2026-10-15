<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->boolean('is_shadow_banned')->default(false)->after('is_banned');
            $table->timestamp('muted_until')->nullable()->after('is_shadow_banned');
            $table->string('last_ip', 45)->nullable()->after('muted_until');
        });
    }

    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_shadow_banned', 'muted_until', 'last_ip']);
        });
    }
};

