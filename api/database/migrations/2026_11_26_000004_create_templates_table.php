<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('html')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
