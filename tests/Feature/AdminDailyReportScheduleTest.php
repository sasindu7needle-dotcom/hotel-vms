<?php

namespace Tests\Feature;

use App\Mail\ScheduledDailyReportMail;
use App\Models\DailyReportSchedule;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminDailyReportScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorised_admin_can_create_a_daily_email_schedule(): void
    {
        $this->withSession($this->scheduleSession())->get(route('admin.configurations.schedules.index'))
            ->assertOk()->assertSee('Schedule Manager')->assertSee('Daily Visitor Details');

        $response = $this->withSession($this->scheduleSession())->post(route('admin.configurations.schedules.store'), [
            'name' => 'Management daily report',
            'email_enabled' => '1',
            'email_time' => '22:00',
            'emails' => ['manager@example.com'],
            'mobiles' => [''],
            'email_reports' => ['visitor_details', 'revenue_detail'],
        ]);

        $response->assertRedirect(route('admin.configurations.schedules.index'));
        $this->assertDatabaseHas('daily_report_schedules', ['name' => 'Management daily report', 'email_enabled' => true, 'email_time' => '22:00']);
        $this->assertDatabaseHas('daily_report_recipients', ['channel' => 'email', 'address' => 'manager@example.com']);
        $this->assertDatabaseHas('daily_report_schedule_reports', ['channel' => 'email', 'report_type' => 'visitor_details']);
    }

    public function test_dispatch_command_emails_a_report_once_and_records_the_delivery(): void
    {
        Mail::fake();
        $schedule = DailyReportSchedule::create(['name' => 'Operations', 'email_enabled' => true, 'email_time' => '08:00', 'is_active' => true]);
        $schedule->recipients()->create(['channel' => 'email', 'address' => 'ops@example.com']);
        $schedule->reports()->create(['channel' => 'email', 'report_type' => 'visitor_summary']);
        VerifiedVisitor::create([
            'verification_id' => (string) str()->uuid(), 'full_name' => 'Daily Report Visitor',
            'payment_status' => 'paid', 'entrance_fee' => '1000.00', 'paid_at' => '2026-08-17 09:00:00',
            'created_at' => '2026-08-17 08:30:00', 'updated_at' => '2026-08-17 08:30:00',
        ]);

        $this->artisan('reports:dispatch-daily', ['--force' => true, '--date' => '2026-08-17'])->assertSuccessful();
        $this->assertDatabaseHas('daily_report_deliveries', ['daily_report_schedule_id' => $schedule->id, 'channel' => 'email', 'report_date' => '2026-08-17', 'status' => 'sent', 'attempts' => 1]);
        Mail::assertSent(ScheduledDailyReportMail::class, fn (ScheduledDailyReportMail $mail) => $mail->hasTo('ops@example.com') && $mail->reports[0]['summary'] === '1 registrations, 0 entries, 0 exits, 0 inside at cut-off.');

        $this->artisan('reports:dispatch-daily', ['--force' => true, '--date' => '2026-08-17'])->assertSuccessful();
        Mail::assertSent(ScheduledDailyReportMail::class, 1);
    }

    private function scheduleSession(): array
    {
        return ['admin_authenticated' => true, 'admin_username' => 'scheduler', 'admin_permissions' => ['Schedule Manager']];
    }
}
