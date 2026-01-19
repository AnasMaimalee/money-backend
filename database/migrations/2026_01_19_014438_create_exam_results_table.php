<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('exam_id');
            $table->uuid('user_id');

            $table->unsignedInteger('total_questions');
            $table->unsignedInteger('total_correct');

            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

// Indexes
            $table->index('exam_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
