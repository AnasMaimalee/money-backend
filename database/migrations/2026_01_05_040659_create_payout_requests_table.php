<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('admin_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_snapshot', 12, 2);

            $table->string('status')->default('pending');
            $table->string('reference')->nullable();

            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // 🔐 Foreign Keys
            $table->foreign('admin_id')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
