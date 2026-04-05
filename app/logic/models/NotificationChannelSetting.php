<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            'smtp_password'   => 'encrypted',
        ];
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
