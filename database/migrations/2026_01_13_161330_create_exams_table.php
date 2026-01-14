<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('total_questions');
            $table->integer('duration_minutes');
            // exams table
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('fee_paid')->default(false); // fee has been successfully debited
            $table->boolean('fee_refunded')->default(false); // fee refunded due to network issues

            $table->enum('status', ['ongoing', 'submitted'])->default('ongoing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
