<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jamb_admission_letter_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('service_id');

            $table->string('email');
            $table->string('phone_number');
            $table->string('profile_code'); // ✅ new field
            $table->string('registration_number')->nullable();

            $table->decimal('customer_price', 10, 2);
            $table->decimal('admin_payout', 10, 2);
            $table->decimal('platform_profit', 10, 2);

            $table->enum('status', ['pending', 'taken', 'completed', 'processing', 'approved', 'rejected'])
                ->default('pending');

            $table->boolean('is_paid')->default(false);

            $table->uuid('taken_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->uuid('rejected_by')->nullable();

            $table->string('result_file')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jamb_admission_letter_requests');
    }
};

