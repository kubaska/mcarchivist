<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('dependencies', function (Blueprint $table) {
            $table->unsignedInteger('version_id');
            $table->unsignedInteger('dependency_version_id')->nullable();
            $table->unsignedInteger('dependency_project_id');
            $table->unsignedTinyInteger('type');

            $table->foreign('version_id')->references('id')->on('versions')->onDelete('cascade');
            $table->foreign('dependency_version_id')->references('id')->on('versions')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dependencies');
    }
};
