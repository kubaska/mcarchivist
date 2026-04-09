<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories_projects', function (Blueprint $table) {
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('project_id');

            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('project_id')->references('id')->on('projects');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mods_mod_categories');
    }
};
