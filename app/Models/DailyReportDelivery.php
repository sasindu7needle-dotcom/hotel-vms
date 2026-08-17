<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportDelivery extends Model
{
    protected $fillable = [
        'daily_report_schedule_id', 'channel', 'report_date', 'status', 'attempts',
        'sent_at', 'error_message', 'metadata',
    ];

    protected $casts = ['sent_at' => 'datetime', 'metadata' => 'array'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DailyReportSchedule::class, 'daily_report_schedule_id');
    }
}
