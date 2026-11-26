<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false)->after('currency');
            $table->text('card')->nullable()->after('auto_renew');
            $table->unsignedTinyInteger('renewal_attempts')->default(0)->after('card');
            $table->timestamp('renewal_attempted_at')->nullable()->after('renewal_attempts');

            $table->index(['auto_renew', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['auto_renew', 'status', 'ends_at']);
            $table->dropColumn(['auto_renew', 'card', 'renewal_attempts', 'renewal_attempted_at']);
        });
    }
};
