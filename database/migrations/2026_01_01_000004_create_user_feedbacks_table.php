<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedbacks', function (Blueprint $table) {
            $table->id('feedback_id');
            $table->string('player_id')->nullable();
            $table->text('content');
            $table->integer('rating')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedbacks');
    }
};
