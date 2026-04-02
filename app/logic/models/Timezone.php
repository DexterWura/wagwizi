<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timezone extends Model
{
    protected $fillable = [
        'identifier',
        'label_short',
        'label_long',
    ];
}
