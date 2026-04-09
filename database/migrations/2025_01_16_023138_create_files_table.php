<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->unsignedInteger('version_id');
            $table->string('remote_id');
            $table->string('component')->nullable();
            $table->unsignedTinyInteger('side')->nullable();
            $table->string('path');
            $table->string('file_name');
            $table->string('original_file_name');
            $table->string('hashes', 2000);
            $table->unsignedBigInteger('size');
            $table->boolean('primary');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('version_id')->references('id')->on('versions');
        });
    }

    public function down()
    {
        Schema::dropIfExists('files');
    }
};
