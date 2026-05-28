<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'reference',
        'idempotency_key',
        'type',
        'status',
        'payer_uid',
        'payee_uid',
        'amount',
        'currency',
        'platform_fee',
        'payee_amount',
        'provider',
        'provider_reference',
        'provider_external_reference',
        'provider_link',
        'failure_reason',
        'metadata',
        'access_granted_at',
        'failed_at',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'platform_fee' => 'integer',
        'payee_amount' => 'integer',
        'metadata' => 'array',
        'access_granted_at' => 'immutable_datetime',
        'failed_at' => 'immutable_datetime',
        'settled_at' => 'immutable_datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function providerTransactions(): HasMany
    {
        return $this->hasMany(ProviderTransaction::class, 'reference', 'reference');
    }
}
