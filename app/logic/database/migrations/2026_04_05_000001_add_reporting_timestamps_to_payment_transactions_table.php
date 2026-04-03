<?php

use App\Models\PaymentTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->text('failure_message')->nullable()->after('failed_at');
        });

        DB::table('payment_transactions')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);

        DB::table('payment_transactions')
            ->where('status', 'failed')
            ->whereNull('failed_at')
            ->update(['failed_at' => DB::raw('updated_at')]);

        PaymentTransaction::query()
            ->where('status', 'failed')
            ->whereNull('failure_message')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $t) {
                    $m = $t->meta;
                    if (is_array($m) && isset($m['error']) && is_string($m['error']) && $m['error'] !== '') {
                        $t->failure_message = $m['error'];
                        $t->save();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'failed_at', 'failure_message']);
        });
    }
};
