<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('remote_id')->nullable();
            $table->string('platform', 100)->nullable();
            $table->unsignedInteger('parent_category_id')->nullable();
            $table->string('name');
            $table->string('group')->nullable();
            $table->unsignedInteger('merge_with_id')->nullable();
            $table->timestamps();

            $table->index(['platform', 'remote_id']);
            $table->foreign('parent_category_id')->references('id')->on('categories');
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};
