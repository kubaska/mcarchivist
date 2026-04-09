<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('library_dependencies', function (Blueprint $table) {
            $table->unsignedInteger('library_id');
            $table->unsignedInteger('version_id');

            $table->foreign('library_id')->references('id')->on('libraries');
            $table->foreign('version_id')->references('id')->on('versions');
        });
    }

    public function down()
    {
        Schema::dropIfExists('library_dependencies');
    }
};
