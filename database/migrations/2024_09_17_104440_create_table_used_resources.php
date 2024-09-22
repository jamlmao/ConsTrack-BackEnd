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
        Schema::create('used_resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->string('used_resource_name');
            $table->integer('resource_qty');
            $table->timestamps();



            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('used_resources');
    }
};
