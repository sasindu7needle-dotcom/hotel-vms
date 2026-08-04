<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifiedVisitor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entrance_fee' => 'decimal:2',
        'checkin_status' => 'boolean',
        'verified_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'identity_reviewed_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class, 'visitor_id');
    }
}
