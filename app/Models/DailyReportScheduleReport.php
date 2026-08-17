<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportScheduleReport extends Model
{
    protected $fillable = ['daily_report_schedule_id', 'channel', 'report_type'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DailyReportSchedule::class, 'daily_report_schedule_id');
    }
}
