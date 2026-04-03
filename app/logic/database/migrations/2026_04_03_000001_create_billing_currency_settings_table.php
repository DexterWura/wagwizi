<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_currency_settings', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3)->default('USD');
            $table->char('default_display_currency', 3)->default('USD');
            $table->char('paynow_checkout_currency', 3)->default('USD');
            $table->json('exchange_rates')->nullable();
            $table->timestamps();
        });

        $base = 'USD';
        $def = 'USD';
        $checkout = 'USD';
        $rates = ['USD' => 1.0];

        $row = DB::table('site_settings')->where('key', 'payment_gateways')->first();
        if ($row !== null && is_string($row->value)) {
            $json = json_decode($row->value, true);
            if (is_array($json)) {
                $pricing = $json['pricing'] ?? [];
                if (is_array($pricing)) {
                    $b = strtoupper(trim((string) ($pricing['base_currency'] ?? 'USD')));
                    $d = strtoupper(trim((string) ($pricing['default_currency'] ?? 'USD')));
                    if (strlen($b) === 3) {
                        $base = $b;
                    }
                    if (strlen($d) === 3) {
                        $def = $d;
                    }
                    $er = $pricing['exchange_rates'] ?? null;
                    if (is_array($er) && $er !== []) {
                        $rates = [];
                        foreach ($er as $k => $v) {
                            if (! is_string($k) && ! is_int($k)) {
                                continue;
                            }
                            $code = strtoupper(substr(trim((string) $k), 0, 3));
                            if (strlen($code) !== 3 || ! is_numeric($v)) {
                                continue;
                            }
                            $rates[$code] = (float) $v;
                        }
                        if ($rates === []) {
                            $rates = ['USD' => 1.0];
                        }
                    }
                }
                $paynow = $json['paynow'] ?? [];
                if (is_array($paynow)) {
                    if (isset($paynow['checkout_currency']) && is_string($paynow['checkout_currency'])) {
                        $c = strtoupper(trim($paynow['checkout_currency']));
                        if (strlen($c) === 3) {
                            $checkout = $c;
                        }
                    } elseif (isset($paynow['accepted_currencies']) && is_array($paynow['accepted_currencies']) && $paynow['accepted_currencies'] !== []) {
                        $first = $paynow['accepted_currencies'][0];
                        if (is_string($first)) {
                            $c = strtoupper(trim($first));
                            if (strlen($c) === 3) {
                                $checkout = $c;
                            }
                        }
                    }
                }
            }
        }

        $rates[$base] = 1.0;

        DB::table('billing_currency_settings')->insert([
            'base_currency'            => $base,
            'default_display_currency'   => $def,
            'paynow_checkout_currency'   => $checkout,
            'exchange_rates'             => json_encode($rates),
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_currency_settings');
    }
};
