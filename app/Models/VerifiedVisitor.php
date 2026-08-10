<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifiedVisitor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entrance_fee' => 'decimal:2',
        'checkin_status' => 'boolean',
        'verified_at' => 'datetime',
        'paid_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'identity_reviewed_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class, 'visitor_id');
    }

    /** The category that controls this visitor's access. */
    public function visitorCategory(): BelongsTo
    {
        return $this->belongsTo(VisitorCategory::class, 'visitor_category_id');
    }

    /** The single event date for which this paid QR pass is valid. */
    public function eventRegistrationDay(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationDay::class);
    }

    /** The exhibitor profile that issued this member's pass, when applicable. */
    public function exhibitorProfile(): BelongsTo
    {
        return $this->belongsTo(ExhibitorProfile::class);
    }
}
