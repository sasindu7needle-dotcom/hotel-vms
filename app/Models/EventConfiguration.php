<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventConfiguration extends Model
{
    public const SINGLETON_KEY = 'active';

    protected $fillable = [
        'singleton_key',
        'event_name',
        'event_location',
        'starts_on',
        'ends_on',
        'organized_by',
        'capacity_limit',
        'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'capacity_limit' => 'integer',
        'is_active' => 'boolean',
    ];

    public function registrationDays(): HasMany
    {
        return $this->hasMany(EventRegistrationDay::class)->orderBy('event_date');
    }
}
