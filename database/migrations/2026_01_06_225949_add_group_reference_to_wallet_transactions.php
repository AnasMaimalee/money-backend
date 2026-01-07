<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // 1️⃣ Add as nullable FIRST (SQLite rule)
            $table->uuid('group_reference')->nullable()->after('reference');
        });

        // 2️⃣ Backfill existing rows
        DB::table('wallet_transactions')
            ->whereNull('group_reference')
            ->update([
                'group_reference' => DB::raw("lower(hex(randomblob(16)))")
            ]);

        // 3️⃣ (Optional) You can leave it nullable for SQLite
        // Making it NOT NULL in SQLite requires table rebuild (not worth it)
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('group_reference');
        });
    }
};
