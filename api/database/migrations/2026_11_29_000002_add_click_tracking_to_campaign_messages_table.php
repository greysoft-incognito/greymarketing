<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table) {
            $table->timestamp('clicked_at')->nullable()->after('opens_count');
            $table->unsignedInteger('clicks_count')->default(0)->after('clicked_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_messages', function (Blueprint $table) {
            $table->dropColumn(['clicked_at', 'clicks_count']);
        });
    }
};
