<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('available_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('cascade');
            $table->date('available_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('available_dates');
    }
};
