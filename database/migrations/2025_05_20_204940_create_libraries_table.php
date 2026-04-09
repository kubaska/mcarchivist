<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('libraries', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('name')->unique();
            $table->string('hashes', 2000);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('libraries');
    }
};
