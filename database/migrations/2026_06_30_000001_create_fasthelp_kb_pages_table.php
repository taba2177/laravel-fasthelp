<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasthelp_kb_pages', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('og_image')->nullable();
            $table->longText('content')->nullable();
            $table->string('content_hash')->nullable()->index();
            $table->longText('embedding')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasthelp_kb_pages');
    }
};
