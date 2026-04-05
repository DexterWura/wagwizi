<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NotificationChannelSetting extends Model
{
    protected $fillable = [
        'email_send_method',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
    ];

    protected function casts(): array
    {
        return [
            'smtp_port'       => 'integer',
        ];
    }

    protected function smtpPassword(): Attribute
    {
        return Attribute::make(
            get: static function ($value): string {
                $raw = (string) ($value ?? '');
                if ($raw === '') {
                    return '';
                }

                try {
                    return (string) Crypt::decryptString($raw);
                } catch (DecryptException) {
                    // Backward compatibility: legacy plaintext values should not crash settings pages.
                    return $raw;
                }
            },
            set: static function ($value): string {
                $raw = (string) ($value ?? '');
                if ($raw === '') {
                    return '';
                }

                return Crypt::encryptString($raw);
            }
        );
    }

    public static function current(): self
    {
        $existing = static::query()->orderBy('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'email_send_method' => 'smtp',
            'smtp_host' => 'mail.dextersoft.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'info@dextersoft.com',
            'smtp_password' => '',
        ]);
    }
}
