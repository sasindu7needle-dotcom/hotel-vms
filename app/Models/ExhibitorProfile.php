<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExhibitorProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'registration_token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(VerifiedVisitor::class);
    }

    public function hasMemberCapacity(): bool
    {
        return $this->members()->count() < $this->member_limit;
    }
}
