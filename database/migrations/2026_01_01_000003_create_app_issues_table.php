<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_issues', function (Blueprint $table) {
            $table->id('issue_id');
            $table->string('severity', 20)->default('error');
            $table->text('message');
            $table->longText('stack_trace')->nullable();
            $table->string('page', 100)->nullable();
            $table->json('device_info')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_issues');
    }
};
