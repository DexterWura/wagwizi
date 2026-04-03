<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationChannelSetting extends Model
{
    protected $fillable = [
        'driver',
        'from_name',
        'from_address',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'smtp_timeout',
        'reply_to',
        'sms_provider',
        'sms_credentials',
        'master_template_html',
    ];

    protected function casts(): array
    {
        return [
            'smtp_port'       => 'integer',
            'smtp_timeout'    => 'integer',
            'smtp_password'   => 'encrypted',
            'sms_credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrFail();
    }
}
