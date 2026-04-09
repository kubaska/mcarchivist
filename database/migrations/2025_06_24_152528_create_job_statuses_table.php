<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('job_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index()->nullable();
            $table->string('uuid')->unique()->nullable();
            $table->string('batch_id')->unique()->nullable();
            $table->string('frontend_id')->nullable();
            $table->unsignedTinyInteger('job_type')->nullable();
            $table->unsignedTinyInteger('state')->default(0);
            $table->string('name');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_statuses');
    }
};
