<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('interval')->default('month');
            $table->unsignedInteger('contacts_limit')->nullable();
            $table->unsignedInteger('emails_limit')->nullable();
            $table->unsignedInteger('sms_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'ends_at']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('workspace_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->unsignedInteger('emails_sent')->default(0);
            $table->unsignedInteger('sms_sent')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_usage');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
