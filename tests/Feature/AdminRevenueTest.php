<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_summary_groups_confirmed_payments_by_date_gate_and_method(): void
    {
        $cashVisitor = $this->paidVisitor('Cash Visitor', 'cash', '1200.00', '2026-07-30 09:15:00');
        $cardVisitor = $this->paidVisitor('Card Visitor', 'visa_master', '1800.00', '2026-07-30 10:20:00');
        GateLog::create(['visitor_id' => $cashVisitor->id, 'gate' => 'A', 'direction' => 'in', 'scanned_at' => Carbon::parse('2026-07-30 09:20:00')]);
        GateLog::create(['visitor_id' => $cardVisitor->id, 'gate' => 'B', 'direction' => 'in', 'scanned_at' => Carbon::parse('2026-07-30 10:30:00')]);

        $this->withSession($this->summarySession())
            ->get(route('admin.revenue.summary', ['date_from' => '2026-07-30', 'date_to' => '2026-07-30', 'gate' => 'A', 'payment_method' => 'cash']))
            ->assertOk()
            ->assertSee('30/07/2026')
            ->assertSee('0000 : 2359')
            ->assertSee('1,200.00')
            ->assertDontSee('1,800.00');
    }

    public function test_revenue_detail_uses_the_recorded_payment_time_and_first_entry_gate(): void
    {
        $visitor = $this->paidVisitor('Revenue Detail Visitor', 'amex', '2500.00', '2026-07-31 14:10:00');
        GateLog::create(['visitor_id' => $visitor->id, 'gate' => 'B', 'direction' => 'in', 'scanned_at' => Carbon::parse('2026-07-31 14:20:00')]);
        GateLog::create(['visitor_id' => $visitor->id, 'gate' => 'C', 'direction' => 'out', 'scanned_at' => Carbon::parse('2026-07-31 16:00:00')]);

        $this->withSession($this->detailSession())
            ->get(route('admin.revenue.detail'))
            ->assertOk()
            ->assertSee('Revenue Detail Visitor')
            ->assertSee('31/07/2026 14:10')
            ->assertSee('2,500.00')
            ->assertSee('American Express')
            ->assertSee('>B<', false);
    }

    public function test_summary_permission_does_not_grant_revenue_detail_access(): void
    {
        $this->withSession($this->summarySession())
            ->get(route('admin.revenue.detail'))
            ->assertRedirect(route('admin.revenue.summary'))
            ->assertSessionHasErrors('access');
    }

    public function test_existing_receipt_manager_access_can_open_revenue_reports(): void
    {
        $this->withSession([
            'admin_authenticated' => true,
            'admin_username' => 'cashier',
            'admin_permissions' => ['Receipt Manager'],
        ])->get(route('admin.revenue.summary'))
            ->assertOk()
            ->assertSee('Revenue Summary')
            ->assertSee('Revenue Detail');
    }

    private function paidVisitor(string $name, string $method, string $fee, string $paidAt): VerifiedVisitor
    {
        return VerifiedVisitor::create([
            'verification_id' => (string) str()->uuid(),
            'full_name' => $name,
            'document_number' => '199012345678',
            'payment_status' => 'paid',
            'payment_method' => $method,
            'entrance_fee' => $fee,
            'paid_at' => Carbon::parse($paidAt),
            'is_blocked' => false,
        ]);
    }

    private function summarySession(): array
    {
        return ['admin_authenticated' => true, 'admin_username' => 'revenue', 'admin_permissions' => ['Revenue Summary']];
    }

    private function detailSession(): array
    {
        return ['admin_authenticated' => true, 'admin_username' => 'revenue', 'admin_permissions' => ['Revenue Detail']];
    }
}
