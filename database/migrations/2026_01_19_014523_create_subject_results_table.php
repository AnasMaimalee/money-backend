<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subject_results', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('exam_result_id');
            $table->uuid('subject_id');

            $table->unsignedInteger('total_questions');
            $table->unsignedInteger('correct_answers');

            $table->timestamps();

            $table->index('exam_result_id');
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_results');
    }
};
