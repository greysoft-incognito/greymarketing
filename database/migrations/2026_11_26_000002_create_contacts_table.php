<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->json('fields')->nullable();
            $table->timestamp('email_unsubscribed_at')->nullable();
            $table->timestamp('sms_unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'email']);
            $table->unique(['workspace_id', 'phone']);
        });

        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_list_contact', function (Blueprint $table) {
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['contact_list_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_list_contact');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('contacts');
    }
};
