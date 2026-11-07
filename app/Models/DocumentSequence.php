<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentSequence extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'document_type',
        'prefix',
        'next_number',
        'padding',
        'reset_period',
        'last_reset_at',
    ];
}
