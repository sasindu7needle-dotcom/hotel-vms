<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReportSchedule extends Model
{
    protected $fillable = ['name', 'email_enabled', 'email_time', 'sms_enabled', 'sms_time', 'is_active'];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(DailyReportRecipient::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DailyReportScheduleReport::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DailyReportDelivery::class);
    }
}
