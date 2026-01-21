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
        Schema::create('cbt_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('subjects_count');
            $table->unsignedTinyInteger('questions_per_subject');
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('exam_fee', 10, 2)->default(0);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_settings');
    }
};
