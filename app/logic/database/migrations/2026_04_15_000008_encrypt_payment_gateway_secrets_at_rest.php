<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $paths = [
        'paynow.integration_key',
        'pesepay.integration_key',
        'pesepay.encryption_key',
        'stripe.secret_key',
        'stripe.webhook_secret',
        'paypal.client_secret',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $row = DB::table('site_settings')->where('key', 'payment_gateways')->first();
        if ($row === null || !is_string($row->value) || trim($row->value) === '') {
            return;
        }

        $decoded = json_decode($row->value, true);
        if (!is_array($decoded)) {
            return;
        }

        $dirty = false;
        foreach ($this->paths as $path) {
            $value = data_get($decoded, $path);
            if (!is_string($value) || trim($value) === '' || str_starts_with($value, 'enc:')) {
                continue;
            }

            data_set($decoded, $path, 'enc:' . Crypt::encryptString(trim($value)));
            $dirty = true;
        }

        if (!$dirty) {
            return;
        }

        DB::table('site_settings')
            ->where('key', 'payment_gateways')
            ->update([
                'value' => json_encode($decoded),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Do not decrypt secrets on rollback.
    }
};

