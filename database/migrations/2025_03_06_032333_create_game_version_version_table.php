<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('game_version_version', function (Blueprint $table) {
            $table->unsignedInteger('game_version_id');
            $table->unsignedInteger('version_id');

            $table->foreign('game_version_id')->references('id')->on('game_versions');
            $table->foreign('version_id')->references('id')->on('versions');
        });
    }

    public function down()
    {
        Schema::dropIfExists('game_version_version');
    }
};
