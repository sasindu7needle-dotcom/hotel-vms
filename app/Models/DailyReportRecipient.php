<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportRecipient extends Model
{
    protected $fillable = ['daily_report_schedule_id', 'channel', 'address'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DailyReportSchedule::class, 'daily_report_schedule_id');
    }
}
