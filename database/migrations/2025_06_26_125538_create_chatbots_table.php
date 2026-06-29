<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('model_id')->nullable(); // e.g., 'gemini-pro', 'gpt-4'
            $table->text('prompt_hint')->nullable();
            $table->float('temperature')->nullable();
            $table->float('top_p')->nullable();
            $table->integer('top_k')->nullable();
            $table->integer('max_output_tokens')->nullable();
            $table->string('rive_script_path')->nullable(); // Path to the RiveScript brain file
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbots');
    }
};
