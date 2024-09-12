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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('client_profiles')->onDelete('cascade');
            $table->text('description');
            $table->dateTime('appointment_datetime');
            $table->enum('status', ['P', 'A', 'R'])->default('P');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
