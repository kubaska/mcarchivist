<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('rulesets', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('name');
            $table->boolean('custom');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rulesets');
    }
};
