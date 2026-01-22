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
        Schema::create('jamb_pin_binding_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->restrictOnDelete();

            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('profile_code');
            $table->string('registration_number')->nullable();

            // 🔒 FINANCIAL (NEVER EXPOSE VIA API)
            $table->decimal('customer_price', 10, 2);
            $table->decimal('admin_payout', 10, 2);
            $table->decimal('platform_profit', 10, 2);

            $table->enum('status', [
                'pending',
                'taken',
                'processing',
                'completed',
                'approved',
                'rejected',
            ])->default('pending');

            $table->boolean('is_paid')->default(false);

            $table->foreignUuid('taken_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('completed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('rejected_by')->nullable()->references('id')->on('users')->nullOnDelete();

            // 🔐 PRIVATE FILE (NOT PUBLIC URL)
            $table->string('result_file')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            // 🔎 PERFORMANCE + SECURITY
            $table->index(['user_id', 'status']);
            $table->index(['completed_by', 'status']);

        });


    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jamb_pin_binding');
    }
};
