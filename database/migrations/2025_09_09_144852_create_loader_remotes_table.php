<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('loader_remotes', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->unsignedInteger('loader_id');
            $table->string('remote_id');
            $table->string('platform', 100);
            $table->timestamps();

            $table->unique(['platform', 'remote_id']);
            $table->foreign('loader_id')->references('id')->on('loaders');
        });
    }

    public function down()
    {
        Schema::dropIfExists('loader_remotes');
    }
};
