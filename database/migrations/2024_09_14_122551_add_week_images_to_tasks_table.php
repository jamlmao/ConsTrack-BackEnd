<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('week1_img')->nullable();
            $table->string('week2_img')->nullable();
            $table->string('week3_img')->nullable();
            $table->string('week4_img')->nullable();
            $table->string('week5_img')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['week1_img', 'week2_img', 'week3_img', 'week4_img']);
        });
    }
};
