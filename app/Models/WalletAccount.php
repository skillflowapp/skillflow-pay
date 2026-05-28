<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WalletAccount extends Model
{
    protected $fillable = [
        'owner_uid',
        'owner_type',
        'owner_name',
        'currency',
        'balance',
        'available_balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected $casts = [
        'balance' => 'integer',
        'available_balance' => 'integer',
        'total_earned' => 'integer',
        'total_withdrawn' => 'integer',
    ];

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }
}
