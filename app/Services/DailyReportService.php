<?php

namespace App\Services;

use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    public const EMAIL_TYPES = ['visitor_details', 'non_checkout_detail', 'revenue_detail'];
    public const SMS_TYPES = ['visitor_summary', 'revenue_summary'];

    /** @return array<string, string> */
    public static function reportTypes(): array
    {
        return [
            'visitor_details' => 'Daily Visitor Details',
            'non_checkout_detail' => 'Non Check-out Detail',
            'revenue_detail' => 'Revenue Detail',
            'visitor_summary' => 'Daily Visitor Summary',
            'revenue_summary' => 'Revenue Summary',
        ];
    }

    /**
     * @param array<int, string> $types
     * @return array<int, array{type:string,label:string,columns:array<int,string>,rows:array<int,array<int,string>>,summary:string}>
     */
    public function build(array $types, CarbonInterface $date): array
    {
        $types = array_values(array_unique(array_intersect($types, array_keys(self::reportTypes()))));
        $reports = [];

        foreach ($types as $type) {
            $reports[] = match ($type) {
                'visitor_details' => $this->visitorDetails($date),
                'non_checkout_detail' => $this->nonCheckoutDetails($date),
                'revenue_detail' => $this->revenueDetails($date),
                'visitor_summary' => $this->visitorSummary($date),
                'revenue_summary' => $this->revenueSummary($date),
            };
        }

        return $reports;
    }

    private function visitorDetails(CarbonInterface $date): array
    {
        $visitors = VerifiedVisitor::query()->whereDate('created_at', $date->toDateString())
            ->orderBy('created_at')->get();

        return $this->report('visitor_details', ['Registered at', 'Name', 'Document', 'Mobile', 'Category', 'Payment'], $visitors->map(fn (VerifiedVisitor $visitor) => [
            $this->dateTime($visitor->created_at), $visitor->full_name ?: 'Unnamed visitor', $visitor->document_number ?: '—',
            $visitor->mobile_number ?: '—', $visitor->category ?: 'Visitor', ucfirst((string) $visitor->payment_status),
        ])->all(), $visitors->count().' visitor registration(s) created.');
    }

    private function nonCheckoutDetails(CarbonInterface $date): array
    {
        $latestLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');
        $entries = GateLog::query()->whereIn('id', $latestLogIds)->where('direction', 'in')
            ->whereDate('scanned_at', $date->toDateString())->with('visitor:id,full_name,document_number,mobile_number')->orderBy('scanned_at')->get();

        return $this->report('non_checkout_detail', ['Entry time', 'Name', 'Document', 'Mobile', 'Gate'], $entries->map(fn (GateLog $entry) => [
            $this->dateTime($entry->scanned_at), $entry->visitor?->full_name ?: 'Unknown visitor',
            $entry->visitor?->document_number ?: '—', $entry->visitor?->mobile_number ?: '—', $entry->gate,
        ])->all(), $entries->count().' visitor(s) remained checked in at the report cut-off.');
    }

    private function revenueDetails(CarbonInterface $date): array
    {
        $paidAt = DB::raw('COALESCE(verified_visitors.paid_at, verified_visitors.updated_at)');
        $visitors = VerifiedVisitor::query()->where('payment_status', 'paid')->whereNotNull('entrance_fee')
            ->whereDate($paidAt, $date->toDateString())->orderBy($paidAt)->get();
        $total = $visitors->sum(fn (VerifiedVisitor $visitor) => (float) $visitor->entrance_fee);

        return $this->report('revenue_detail', ['Payment time', 'Name', 'Document', 'Method', 'Amount (LKR)'], $visitors->map(fn (VerifiedVisitor $visitor) => [
            $this->dateTime($visitor->paid_at ?: $visitor->updated_at), $visitor->full_name ?: 'Unnamed visitor',
            $visitor->document_number ?: '—', $this->paymentMethod($visitor->payment_method), number_format((float) $visitor->entrance_fee, 2),
        ])->all(), $visitors->count().' confirmed payment(s), LKR '.number_format($total, 2).'.');
    }

    private function visitorSummary(CarbonInterface $date): array
    {
        $until = $date->copy()->endOfDay();
        $latestLogIds = GateLog::query()->where('scanned_at', '<=', $until)->selectRaw('MAX(id)')->groupBy('visitor_id');
        $registered = VerifiedVisitor::query()->whereDate('created_at', $date->toDateString())->count();
        $entered = GateLog::query()->where('direction', 'in')->whereDate('scanned_at', $date->toDateString())->count();
        $exited = GateLog::query()->where('direction', 'out')->whereDate('scanned_at', $date->toDateString())->count();
        $inside = GateLog::query()->whereIn('id', $latestLogIds)->where('direction', 'in')->count();

        return $this->report('visitor_summary', ['Metric', 'Count'], [
            ['Visitor registrations', (string) $registered], ['Entries recorded', (string) $entered], ['Exits recorded', (string) $exited], ['Inside at cut-off', (string) $inside],
        ], "{$registered} registrations, {$entered} entries, {$exited} exits, {$inside} inside at cut-off.");
    }

    private function revenueSummary(CarbonInterface $date): array
    {
        $paidAt = DB::raw('COALESCE(paid_at, updated_at)');
        $rows = VerifiedVisitor::query()->where('payment_status', 'paid')->whereNotNull('entrance_fee')
            ->whereDate($paidAt, $date->toDateString())->selectRaw('payment_method, COUNT(*) as payments, SUM(entrance_fee) as total')
            ->groupBy('payment_method')->orderBy('payment_method')->get();
        $total = $rows->sum(fn ($row) => (float) $row->total);
        $payments = $rows->sum('payments');

        return $this->report('revenue_summary', ['Payment method', 'Payments', 'Revenue (LKR)'], $rows->map(fn ($row) => [
            $this->paymentMethod($row->payment_method), (string) $row->payments, number_format((float) $row->total, 2),
        ])->all(), "{$payments} confirmed payment(s), total LKR ".number_format($total, 2).'.');
    }

    private function report(string $type, array $columns, array $rows, string $summary): array
    {
        return ['type' => $type, 'label' => self::reportTypes()[$type], 'columns' => $columns, 'rows' => $rows, 'summary' => $summary];
    }

    private function paymentMethod(?string $method): string
    {
        return match ($method) { 'visa_master' => 'Visa / MasterCard', 'amex' => 'American Express', 'cash' => 'Cash', default => 'Not recorded' };
    }

    private function dateTime($dateTime): string
    {
        return $dateTime ? $dateTime->format('d/m/Y H:i') : '—';
    }
}
