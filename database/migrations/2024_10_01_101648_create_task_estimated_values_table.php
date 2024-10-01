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
        Schema::create('task_estimated_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->decimal('estimated_resource_value', 10, 2);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('task_id')->references('id')->on('project_tasks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('task_estimated_values');
    }
};
