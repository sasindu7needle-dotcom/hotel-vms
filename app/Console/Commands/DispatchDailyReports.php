<?php

namespace App\Console\Commands;

use App\Services\DailyReportDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchDailyReports extends Command
{
    protected $signature = 'reports:dispatch-daily {--date= : Report date in YYYY-MM-DD format} {--force : Send all active channels now, ignoring their scheduled times}';
    protected $description = 'Send due daily visitor and revenue reports.';

    public function handle(DailyReportDispatcher $dispatcher): int
    {
        $date = $this->option('date') ? Carbon::createFromFormat('Y-m-d', (string) $this->option('date'))->startOfDay() : null;
        $result = $dispatcher->dispatchDue(now(), (bool) $this->option('force'), $date);
        $this->info("Daily reports: {$result['sent']} sent, {$result['failed']} failed, {$result['skipped']} skipped.");

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
