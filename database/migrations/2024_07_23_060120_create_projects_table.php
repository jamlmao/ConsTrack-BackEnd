<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
           
            $table->unsignedBigInteger('client_id');
          
            $table->string('status');
            $table->date('completion_date');
            $table->string('pj_image')->nullable();
            $table->string('pj_pdf')->nullable();
            $table->date('starting_date');
            $table->integer('totalBudget');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('client_profiles')->onDelete('cascade');
          
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('projects');
    }
}