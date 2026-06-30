<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('active_provider')->default('openrouter');
            $table->text('openrouter_api_key')->nullable();
            $table->string('openrouter_model')->nullable();
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_settings');
    }
}
