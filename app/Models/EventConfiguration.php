<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
