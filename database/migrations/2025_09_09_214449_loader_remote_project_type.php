<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('loader_remote_project_type', function (Blueprint $table) {
            $table->unsignedInteger('loader_remote_id');
            $table->unsignedInteger('project_type_id');

            $table->foreign('loader_remote_id')->references('id')->on('loader_remotes');
            $table->foreign('project_type_id')->references('id')->on('project_types');
        });
    }

    public function down()
    {
        Schema::dropIfExists('loader_remote_project_type');
    }
};
