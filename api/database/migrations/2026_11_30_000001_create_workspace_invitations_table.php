<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['workspace_id', 'email']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('seats_limit')->nullable()->after('sms_limit');
        });

        foreach (['free' => 1, 'starter' => 3, 'growth' => 10] as $slug => $seats) {
            DB::table('plans')->where('slug', $slug)->update(['seats_limit' => $seats]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('seats_limit');
        });

        Schema::dropIfExists('workspace_invitations');
    }
};
