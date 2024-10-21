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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('client'); 
            $table->timestamp('last_logged_in_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the staff_profiles table exists before dropping the foreign key
        if (Schema::hasTable('staff_profiles')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('staff_profiles', 'user_id')) {
                    $table->dropForeign(['user_id']);
                }
            });
        }

        Schema::dropIfExists('users');
    }
};
