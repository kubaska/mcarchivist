<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('category_project_type', function (Blueprint $table) {
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('project_type_id');

            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('project_type_id')->references('id')->on('project_types');
        });
    }

    public function down()
    {
        Schema::dropIfExists('category_project_type');
    }
};
