<?php

namespace App\Models;

use App\Enums\ResetPeriod;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'document_type',
        'prefix',
        'next_number',
        'padding',
        'reset_period',
        'last_reset_at',
    ];

    protected $casts = [
        'reset_period' => ResetPeriod::class,
    ];
}
