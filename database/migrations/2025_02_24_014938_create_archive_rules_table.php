<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('archive_rules', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->morphs('ruleable');
            $table->unsignedInteger('loader_id')->nullable();
            $table->string('game_version_from')->nullable();
            $table->string('game_version_to')->nullable();
            $table->boolean('with_snapshots');
            $table->unsignedTinyInteger('release_type')->nullable();
            $table->boolean('release_type_priority');
            $table->unsignedSmallInteger('count');
            $table->boolean('sorting');
            $table->unsignedTinyInteger('dependencies');
            $table->boolean('all_files');
            $table->timestamps();

            $table->foreign('loader_id')->references('id')->on('loaders');
        });
    }

    public function down()
    {
        Schema::dropIfExists('archive_rules');
    }
};
