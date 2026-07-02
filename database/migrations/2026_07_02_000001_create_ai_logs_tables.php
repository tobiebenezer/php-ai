<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiLogsTables extends Migration
{
    public function up()
    {
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('profile')->nullable();
            $table->string('model')->nullable();
            $table->longText('prompt')->nullable();
            $table->longText('response_content')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_tool_call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_log_id');
            $table->string('tool_name');
            $table->text('arguments')->nullable();
            $table->longText('result')->nullable();
            $table->string('status')->default('success');
            $table->text('exception_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('request_log_id')
                ->references('id')
                ->on('ai_request_logs')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_tool_call_logs');
        Schema::dropIfExists('ai_request_logs');
    }
}
