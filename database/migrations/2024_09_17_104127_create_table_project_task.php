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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('category_id');
            $table->string('pt_status');
            $table->string('pt_task_name');
            $table->timestamp('pt_updated_at')->useCurrent();
            $table->dateTime('pt_completion_date');
            $table->dateTime('pt_starting_date');
            $table->string('pt_photo_task')->nullable();
            $table->string('pt_file_task')->nullable();
            $table->integer('pt_allocated_budget');
            $table->string('update_img')->nullable(); // Add the new column
            $table->string('update_file')->nullable();
            $table->string('week1_img')->nullable();
            $table->string('week2_img')->nullable();
            $table->string('week3_img')->nullable();
            $table->string('week4_img')->nullable();
            $table->string('week5_img')->nullable();
            $table->timestamps();


            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('project_tasks');
    }
};