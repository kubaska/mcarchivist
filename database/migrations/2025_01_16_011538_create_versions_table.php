<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('versions', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->morphs('versionable');
            $table->string('platform', 100);
            $table->string('remote_id');
            $table->string('version');
            $table->string('components', 1000)->nullable();
            $table->unsignedTinyInteger('type');
            $table->text('changelog')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'remote_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('versions');
    }
};
