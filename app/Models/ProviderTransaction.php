<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ProviderTransaction extends Model
{
    protected $fillable = [
        'provider',
        'direction',
        'reference',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'request_payload',
        'response_payload',
        'http_status',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'http_status' => 'integer',
    ];
}
