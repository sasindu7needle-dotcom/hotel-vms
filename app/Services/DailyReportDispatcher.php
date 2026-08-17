<?php

namespace App\Services;

use App\Mail\ScheduledDailyReportMail;
use App\Models\DailyReportDelivery;
use App\Models\DailyReportSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DailyReportDispatcher
{
    public function __construct(private DailyReportService $reports, private SmsReportSender $sms) {}

    /** @return array{sent:int,failed:int,skipped:int} */
    public function dispatchDue(CarbonInterface $now, bool $force = false, ?CarbonInterface $reportDate = null): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $date = ($reportDate ?: $now)->toDateString();
        $schedules = DailyReportSchedule::query()->where('is_active', true)->with(['recipients', 'reports'])->get();

        foreach ($schedules as $schedule) {
            foreach (['email', 'sms'] as $channel) {
                if (! $this->isDue($schedule, $channel, $now, $force)) {
                    continue;
                }

                $outcome = $this->deliver($schedule, $channel, $date);
                $result[$outcome]++;
            }
        }

        return $result;
    }

    private function isDue(DailyReportSchedule $schedule, string $channel, CarbonInterface $now, bool $force): bool
    {
        $enabled = $channel === 'email' ? $schedule->email_enabled : $schedule->sms_enabled;
        $time = $channel === 'email' ? $schedule->email_time : $schedule->sms_time;

        return $enabled && $time && ($force || substr((string) $time, 0, 5) <= $now->format('H:i'));
    }

    /** @return 'sent'|'failed'|'skipped' */
    private function deliver(DailyReportSchedule $schedule, string $channel, string $date): string
    {
        $recipients = $schedule->recipients->where('channel', $channel)->pluck('address')->values()->all();
        $types = $schedule->reports->where('channel', $channel)->pluck('report_type')->values()->all();
        if (empty($recipients) || empty($types)) {
            return 'skipped';
        }

        $delivery = DailyReportDelivery::query()->firstOrNew([
            'daily_report_schedule_id' => $schedule->id,
            'channel' => $channel,
            'report_date' => $date,
        ]);
        if ($delivery->exists && ($delivery->status === 'sent' || ($delivery->status === 'processing' && $delivery->updated_at?->gt(now()->subMinutes(30))) || $delivery->attempts >= 3)) {
            return 'skipped';
        }

        $delivery->fill([
            'status' => 'processing',
            'attempts' => min(255, ((int) $delivery->attempts) + 1),
            'error_message' => null,
            'metadata' => ['recipients' => $recipients, 'reports' => $types],
        ])->save();

        try {
            $reports = $this->reports->build($types, now()->parse($date));
            if ($channel === 'email') {
                Mail::to($recipients)->send(new ScheduledDailyReportMail($schedule->name, now()->parse($date)->format('d M Y'), $reports));
            } else {
                $message = $this->smsMessage($schedule->name, $date, $reports);
                foreach ($recipients as $recipient) {
                    $this->sms->send($recipient, $message);
                }
            }

            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
            return 'sent';
        } catch (Throwable $exception) {
            report($exception);
            $delivery->update(['status' => 'failed', 'error_message' => str($exception->getMessage())->limit(1000)]);
            return 'failed';
        }
    }

    private function smsMessage(string $scheduleName, string $date, array $reports): string
    {
        $summary = collect($reports)->map(fn (array $report) => "{$report['label']}: {$report['summary']}")->implode(' ');
        return str("{$scheduleName} daily report ({$date}). {$summary}")->limit(1000, '…')->toString();
    }
}
