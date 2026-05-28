<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WithdrawalRequest extends Model
{
    protected $fillable = [
        'public_id',
        'reference',
        'type',
        'status',
        'owner_uid',
        'owner_name',
        'requested_amount',
        'platform_fee',
        'platform_fee_percent',
        'payout_amount',
        'currency',
        'payment_method',
        'recipient_phone',
        'provider',
        'provider_reference',
        'provider_external_reference',
        'failure_reason',
        'metadata',
        'reserved_at',
        'processed_at',
        'completed_at',
        'reversed_at',
    ];

    protected $casts = [
        'requested_amount' => 'integer',
        'platform_fee' => 'integer',
        'platform_fee_percent' => 'decimal:2',
        'payout_amount' => 'integer',
        'metadata' => 'array',
        'reserved_at' => 'immutable_datetime',
        'processed_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
        'reversed_at' => 'immutable_datetime',
    ];
}
