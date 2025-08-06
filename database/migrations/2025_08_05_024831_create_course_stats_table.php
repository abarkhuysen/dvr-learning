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
        Schema::create('course_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('total_enrollments')->default(0);
            $table->integer('active_enrollments')->default(0);
            $table->decimal('average_completion_rate', 5, 2)->default(0);
            $table->timestamp('last_updated');
            $table->timestamps();

            $table->unique('course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_stats');
    }
};
