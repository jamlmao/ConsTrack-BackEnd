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
        // Check if the table already exists
        if (!Schema::hasTable('client_profiles')) {
            Schema::create('client_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('first_name');
                $table->string('last_name');
                $table->enum('sex', ['M', 'F']);
                $table->string('address');
                $table->string('city');
                $table->string('country');
                $table->string('zipcode');
                $table->string('phone_number');
                $table->timestamps();
                
                
                // Add foreign key constraints
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
             
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the client_profiles table first to avoid foreign key constraint issues
        Schema::dropIfExists('client_profiles');
    }
};
