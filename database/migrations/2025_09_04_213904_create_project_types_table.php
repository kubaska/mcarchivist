<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('name');
            $table->unsignedTinyInteger('type');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_types');
    }
};
