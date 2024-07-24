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
        Schema::create('project_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action'); 
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('project_id'); 
            $table->timestamps();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            // Add foreign key constraints
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
        {
            Schema::dropIfExists('project_logs');
        } 
};
