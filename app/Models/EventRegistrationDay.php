<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistrationDay extends Model
{
    protected $fillable = [
        'event_configuration_id',
        'label',
        'event_date',
        'entrance_fee',
        'is_active',
    ];

    protected $casts = [
        'event_date' => 'date',
        'entrance_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function eventConfiguration(): BelongsTo
    {
        return $this->belongsTo(EventConfiguration::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(VerifiedVisitor::class);
    }

    public function isOpenForRegistration(): bool
    {
        return $this->is_active && $this->event_date->endOfDay()->isFuture();
    }
}
