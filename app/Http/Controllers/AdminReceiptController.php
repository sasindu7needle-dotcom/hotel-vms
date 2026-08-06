<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $visitor = null;

        if ($search !== '') {
            $normalized = strtoupper(preg_replace('/\s+/', '', $search));
            $visitor = VerifiedVisitor::query()
                ->where(function ($query) use ($search, $normalized) {
                    $query->where('document_number', $normalized)
                        ->orWhere('mobile_number', $search);
                })
                ->latest()
                ->first();
        }

        return view('admin.receipts.index', compact('search', 'visitor'));
    }

    public function confirm(Request $request, VerifiedVisitor $visitor): RedirectResponse
    {
        $validated = $request->validate([
            'entrance_fee' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_method' => ['required', 'in:cash,visa_master,amex'],
        ]);

        $visitor->update([
            'entrance_fee' => $validated['entrance_fee'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'paid',
            'paid_at' => $visitor->paid_at ?: now(),
            'registration_status' => 'paid',
        ]);

        return redirect()
            ->route('admin.receipts.index', ['search' => $visitor->document_number ?: $visitor->mobile_number])
            ->with('status', 'Payment confirmed for '.$visitor->full_name.'.');
    }
}
