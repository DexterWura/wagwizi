<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 24)->nullable()->unique()->after('email');
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
        });

        // Backfill codes for existing users so referral links are available immediately.
        DB::table('users')
            ->select(['id'])
            ->whereNull('referral_code')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $code = null;
                    do {
                        $code = strtoupper(Str::random(10));
                    } while (DB::table('users')->where('referral_code', $code)->exists());

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['referral_code' => $code]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropUnique('users_referral_code_unique');
            $table->dropColumn('referral_code');
        });
    }
};

