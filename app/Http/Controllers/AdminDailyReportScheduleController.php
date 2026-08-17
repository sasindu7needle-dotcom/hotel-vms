<?php

namespace App\Http\Controllers;

use App\Models\DailyReportSchedule;
use App\Services\DailyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminDailyReportScheduleController extends Controller
{
    public function index(): View
    {
        return view('admin.configurations.schedules', [
            'schedules' => DailyReportSchedule::query()->with(['recipients', 'reports', 'deliveries' => fn ($query) => $query->latest()->limit(5)])->latest()->get(),
            'reportTypes' => DailyReportService::reportTypes(),
            'emailTypes' => DailyReportService::EMAIL_TYPES,
            'smsTypes' => DailyReportService::SMS_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schedule = DB::transaction(function () use ($request) {
            $schedule = DailyReportSchedule::create($this->scheduleData($request));
            $this->saveChannelData($schedule, $request);
            return $schedule;
        });

        return redirect()->route('admin.configurations.schedules.index')->with('status', "Schedule '{$schedule->name}' was created.");
    }

    public function update(Request $request, DailyReportSchedule $schedule): RedirectResponse
    {
        DB::transaction(function () use ($request, $schedule) {
            $data = $this->scheduleData($request);
            unset($data['is_active']);
            $schedule->update($data);
            $schedule->recipients()->delete();
            $schedule->reports()->delete();
            $this->saveChannelData($schedule, $request);
        });

        return redirect()->route('admin.configurations.schedules.index')->with('status', "Schedule '{$schedule->name}' was updated.");
    }

    public function toggle(DailyReportSchedule $schedule): RedirectResponse
    {
        $schedule->update(['is_active' => ! $schedule->is_active]);
        return back()->with('status', "Schedule '{$schedule->name}' is now ".($schedule->is_active ? 'active.' : 'paused.'));
    }

    public function destroy(DailyReportSchedule $schedule): RedirectResponse
    {
        $name = $schedule->name;
        $schedule->delete();
        return back()->with('status', "Schedule '{$name}' was removed.");
    }

    private function scheduleData(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email_enabled' => ['nullable', 'boolean'], 'email_time' => ['nullable', 'date_format:H:i'],
            'sms_enabled' => ['nullable', 'boolean'], 'sms_time' => ['nullable', 'date_format:H:i'],
            'emails' => ['nullable', 'array'], 'emails.*' => ['nullable', 'email:rfc', 'max:255'],
            'mobiles' => ['nullable', 'array'], 'mobiles.*' => ['nullable', 'string', 'max:30'],
            'email_reports' => ['nullable', 'array'], 'email_reports.*' => ['in:'.implode(',', DailyReportService::EMAIL_TYPES)],
            'sms_reports' => ['nullable', 'array'], 'sms_reports.*' => ['in:'.implode(',', DailyReportService::SMS_TYPES)],
        ]);

        $emailEnabled = $request->boolean('email_enabled');
        $smsEnabled = $request->boolean('sms_enabled');
        $emails = $this->addresses((array) $request->input('emails', []), 'email');
        $mobiles = $this->addresses((array) $request->input('mobiles', []), 'sms');
        $emailReports = array_values(array_unique((array) $request->input('email_reports', [])));
        $smsReports = array_values(array_unique((array) $request->input('sms_reports', [])));

        if (! $emailEnabled && ! $smsEnabled) throw ValidationException::withMessages(['schedule' => 'Enable Email, SMS, or both.']);
        if ($emailEnabled && (! $request->input('email_time') || empty($emails) || empty($emailReports))) throw ValidationException::withMessages(['email' => 'Email schedules need a time, recipient, and at least one report.']);
        if ($smsEnabled && (! $request->input('sms_time') || empty($mobiles) || empty($smsReports))) throw ValidationException::withMessages(['sms' => 'SMS schedules need a time, recipient, and at least one report.']);

        $request->merge(['_schedule_emails' => $emails, '_schedule_mobiles' => $mobiles, '_schedule_email_reports' => $emailReports, '_schedule_sms_reports' => $smsReports]);
        return ['name' => trim($request->input('name')), 'email_enabled' => $emailEnabled, 'email_time' => $emailEnabled ? $request->input('email_time') : null, 'sms_enabled' => $smsEnabled, 'sms_time' => $smsEnabled ? $request->input('sms_time') : null, 'is_active' => true];
    }

    private function saveChannelData(DailyReportSchedule $schedule, Request $request): void
    {
        foreach ($request->input('_schedule_emails', []) as $email) $schedule->recipients()->create(['channel' => 'email', 'address' => $email]);
        foreach ($request->input('_schedule_mobiles', []) as $mobile) $schedule->recipients()->create(['channel' => 'sms', 'address' => $mobile]);
        foreach ($request->input('_schedule_email_reports', []) as $type) $schedule->reports()->create(['channel' => 'email', 'report_type' => $type]);
        foreach ($request->input('_schedule_sms_reports', []) as $type) $schedule->reports()->create(['channel' => 'sms', 'report_type' => $type]);
    }

    /** @return array<int, string> */
    private function addresses(array $addresses, string $channel): array
    {
        $addresses = array_filter(array_map(
            static fn ($address) => is_string($address) ? trim($address) : '',
            $addresses
        ));
        if ($channel === 'sms') {
            $addresses = array_map(fn (string $number) => preg_replace('/[\\s\\-()]/', '', $number), $addresses);
            foreach ($addresses as $number) if (! preg_match('/^\\+?[0-9]{7,20}$/', $number)) throw ValidationException::withMessages(['mobiles' => 'Each mobile number must contain 7–20 digits and may start with +.']);
        }
        return array_values(array_unique($addresses));
    }
}
