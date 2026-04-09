<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('remote_id');
            $table->string('platform', 100);
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'remote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('authors');
    }
};
