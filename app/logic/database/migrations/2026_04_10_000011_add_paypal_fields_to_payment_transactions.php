<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasPaymentId = Schema::hasColumn('payment_transactions', 'paypal_payment_id');
        $hasPayerId = Schema::hasColumn('payment_transactions', 'paypal_payer_id');

        Schema::table('payment_transactions', function (Blueprint $table) use ($hasPaymentId, $hasPayerId): void {
            if (! $hasPaymentId) {
                $table->string('paypal_payment_id', 120)->nullable()->after('paynow_reference')->index();
            }
            if (! $hasPayerId) {
                $table->string('paypal_payer_id', 120)->nullable()->after('paypal_payment_id')->index();
            }
        });
    }

    public function down(): void
    {
        $hasPayerId = Schema::hasColumn('payment_transactions', 'paypal_payer_id');
        $hasPaymentId = Schema::hasColumn('payment_transactions', 'paypal_payment_id');

        Schema::table('payment_transactions', function (Blueprint $table) use ($hasPayerId, $hasPaymentId): void {
            if ($hasPayerId) {
                $table->dropIndex(['paypal_payer_id']);
                $table->dropColumn('paypal_payer_id');
            }
            if ($hasPaymentId) {
                $table->dropIndex(['paypal_payment_id']);
                $table->dropColumn('paypal_payment_id');
            }
        });
    }
};

