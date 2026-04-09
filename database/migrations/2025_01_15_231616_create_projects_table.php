<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->unsignedInteger('master_project_id');
            $table->string('platform', 100);
            $table->string('remote_id');
            $table->string('name');
            $table->unsignedBigInteger('downloads');
            $table->string('project_url', 500);
            $table->string('logo', 500)->nullable();
            $table->string('summary');
            $table->text('description');
            $table->dateTime('last_version_check')->nullable();
            $table->dateTime('version_check_available_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'remote_id']);
            $table->foreign('master_project_id')->references('id')->on('master_projects');
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
};
