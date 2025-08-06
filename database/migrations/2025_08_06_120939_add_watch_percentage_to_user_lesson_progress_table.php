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
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->decimal('watch_percentage', 5, 2)->default(0)->after('watch_time_seconds');
            $table->timestamp('last_watched_at')->nullable()->after('watch_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->dropColumn(['watch_percentage', 'last_watched_at']);
        });
    }
};
