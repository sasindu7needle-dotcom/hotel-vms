<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifiedVisitor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entrance_fee' => 'decimal:2',
        'checkin_status' => 'boolean',
        'verified_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'face_match_score' => 'decimal:2',
        'face_detection_confidence' => 'decimal:2',
        'face_verified_at' => 'datetime',
        'identity_reviewed_at' => 'datetime',
    ];
}
