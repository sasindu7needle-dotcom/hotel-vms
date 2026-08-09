<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use App\Models\VisitorCategory;
use App\Services\GateLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitorCategoryMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_a_gate_valid_qr_pass_for_a_category_member(): void
    {
        $category = VisitorCategory::create([
            'name' => 'Exhibitor',
            'code' => 'exhibitor',
            'badge_color' => '#c8e063',
            'entrance_fee' => 0,
            'is_active' => true,
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->post(route('admin.configurations.categories.members.store', $category), [
                'full_name' => 'Ayesha Perera',
                'email' => 'ayesha@example.test',
                'mobile_number' => '+94771234567',
                'company' => 'Example Exhibitions',
                'occupation' => 'Exhibitor',
            ])
            ->assertRedirect();

        $member = VerifiedVisitor::firstOrFail();
        $this->assertSame($category->id, $member->visitor_category_id);
        $this->assertSame('Exhibitor', $member->category);
        $this->assertSame('paid', $member->payment_status);
        $this->assertNotEmpty($member->verification_id);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.configurations.categories.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('Add Exhibitor people')
            ->assertSee('Ayesha Perera')
            ->assertSee('Open QR pass');

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.visitors.badge', $member))
            ->assertOk()
            ->assertSee($member->verification_id)
            ->assertSee('<svg', false);

        $this->assertSame('in', app(GateLogService::class)->scan($member->verification_id, 'A', null, 'in')->direction);
    }

    public function test_category_status_and_access_schedule_are_enforced_at_the_gate(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $category = VisitorCategory::create([
            'name' => 'Staff',
            'code' => 'staff',
            'badge_color' => '#c8e063',
            'entrance_fee' => 0,
            'is_active' => true,
            'access_schedule' => [['date' => '2026-08-10', 'from' => '10:00', 'to' => '18:00']],
        ]);
        $member = VerifiedVisitor::create([
            'verification_id' => (string) \Illuminate\Support\Str::uuid(),
            'visitor_category_id' => $category->id,
            'full_name' => 'Scheduled Staff',
            'category' => 'Staff',
            'payment_status' => 'paid',
            'registration_status' => 'registered',
        ]);

        try {
            app(GateLogService::class)->scan($member->verification_id, 'A', null, 'in');
            $this->fail('A category member outside the configured entry time should be rejected.');
        } catch (\App\Exceptions\GateScanException $exception) {
            $this->assertSame('outside_category_schedule', $exception->reason);
        }

        Carbon::setTestNow('2026-08-10 10:00:00');
        $this->assertSame('in', app(GateLogService::class)->scan($member->verification_id, 'A', null, 'in')->direction);

        Carbon::setTestNow();
    }
}
