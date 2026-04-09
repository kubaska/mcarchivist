<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('authors_projects', function (Blueprint $table) {
            $table->unsignedInteger('author_id');
            $table->unsignedInteger('project_id');
            $table->string('role')->nullable();

            $table->foreign('author_id')->references('id')->on('authors');
            $table->foreign('project_id')->references('id')->on('projects');
        });
    }

    public function down()
    {
        Schema::dropIfExists('authors_projects');
    }
};
