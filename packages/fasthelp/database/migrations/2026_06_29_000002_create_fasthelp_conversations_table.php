<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasthelp_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->index();
            $table->nullableMorphs('client');
            $table->unsignedBigInteger('visitor_id')->nullable()->index();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('assigned_agent_id')->nullable()->index();
            $table->string('current_url')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('visitor_id')
                ->references('id')->on('fasthelp_visitors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasthelp_conversations');
    }
};
