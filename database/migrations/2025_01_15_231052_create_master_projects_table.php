<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('master_projects', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('name');
            $table->unsignedInteger('preferred_project_id')->nullable();
            $table->string('archive_dir')->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('master_projects');
    }
};
