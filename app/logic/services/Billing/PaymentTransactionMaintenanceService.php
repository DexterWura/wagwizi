<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;

final class PaymentTransactionMaintenanceService
{
    /**
     * Mark very old pending transactions as failed to reduce confusion and keep the ledger tidy.
     * This does NOT affect access (access is granted only on completed payments).
     *
     * @return int number of rows updated
     */
    public function expireStalePendingTransactions(int $olderThanMinutes = 1440): int
    {
        $olderThanMinutes = max(5, $olderThanMinutes);

        return PaymentTransaction::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => 'Checkout expired.',
            ]);
    }
}

