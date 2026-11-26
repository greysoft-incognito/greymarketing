<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->unsignedInteger('opens_count')->default(0)->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'opens_count']);
        });
    }
};
