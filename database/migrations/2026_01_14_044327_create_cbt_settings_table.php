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
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('subjects_count')->default(4);
            $table->unsignedTinyInteger('questions_per_subject')->default(15);
            $table->unsignedSmallInteger('duration_minutes')->default(120);
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
