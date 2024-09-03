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
        Schema::create('staff_profiles',function(Blueprint $table){
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

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
