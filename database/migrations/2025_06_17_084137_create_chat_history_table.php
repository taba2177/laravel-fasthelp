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
        Schema::create('chat_histories', function (Blueprint $table) {
        $table->id();
        $table->string('user_query_hash', 191)->comment('A hash of the user query for quick lookup');
        $table->unique(['user_query_hash'], 'chat_history_user_query_hash_unique');
        $table->text('user_query')->comment('The original user query');
        $table->json('bot_response')->comment('The bot\'s response, potentially including sources/structured data');
        $table->timestamp('expires_at')->nullable()->comment('When this cache entry should expire');
        $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_history');
    }
};
