<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('game_versions', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('name');
            $table->unsignedTinyInteger('type');
            $table->boolean('official');
            $table->dateTime('released_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('game_versions');
    }
};
