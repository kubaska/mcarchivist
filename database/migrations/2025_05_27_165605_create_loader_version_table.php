<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('loader_version', function (Blueprint $table) {
            $table->unsignedInteger('loader_id');
            $table->unsignedInteger('version_id');

            $table->foreign('loader_id')->references('id')->on('loaders');
            $table->foreign('version_id')->references('id')->on('versions');
        });
    }

    public function down()
    {
        Schema::dropIfExists('loader_version');
    }
};
