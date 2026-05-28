<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ProviderEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_key',
        'event_type',
        'reference',
        'status',
        'payload',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'immutable_datetime',
    ];
}
