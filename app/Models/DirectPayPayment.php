<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectPayPayment extends Model
{
    protected $table = 'directpay_payments';

    protected $guarded = [];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'safe_gateway_response' => 'array',
        'verified_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(VerifiedVisitor::class, 'verified_visitor_id');
    }
}
