<?php

namespace App\Services;

use App\Models\VerifiedVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VisitorRegistrationResumeService
{
    public function findByNic(string $documentNumber, ?string $exceptVerificationId = null): ?VerifiedVisitor
    {
        if (! Schema::hasTable('verified_visitors')) {
            return null;
        }

        $nic = $this->normalizeNic($documentNumber);
        if ($nic === '') {
            return null;
        }

        $normalizedDocumentNumber = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(document_number, ''), ' ', ''), '-', ''), '.', ''), '/', ''))";
        $query = VerifiedVisitor::query()
            ->with(['eventRegistrationDay', 'visitorCategory'])
            ->whereRaw("LOWER(COALESCE(document_type, '')) IN (?, ?)", ['nic', 'driving_license']);
        if (Schema::hasColumn('verified_visitors', 'nic_registration_key')) {
            $query->where(function ($query) use ($nic, $normalizedDocumentNumber) {
                $query->where('nic_registration_key', $nic)
                    ->orWhereRaw("{$normalizedDocumentNumber} = ?", [$nic]);
            });
        } else {
            $query->whereRaw("{$normalizedDocumentNumber} = ?", [$nic]);
        }
        if (filled($exceptVerificationId)) {
            $query->where('verification_id', '!=', $exceptVerificationId);
        }

        // Historical databases may contain more than one row for the same NIC.
        // A completed payment is canonical; otherwise resume the first claim.
        return $query
            ->orderByRaw("CASE WHEN LOWER(COALESCE(payment_status, '')) = 'paid' THEN 0 ELSE 1 END")
            ->oldest('id')
            ->first();
    }

    /** Restore the original unpaid registration and return its payment URL. */
    public function resumePayment(Request $request, VerifiedVisitor $visitor): string
    {
        $visitor->loadMissing(['eventRegistrationDay', 'visitorCategory']);
        $visitor->update([
            'payment_method' => 'visa_master',
            'payment_status' => 'pending',
            'registration_status' => 'payment_pending',
        ]);

        $this->restoreSession($request, $visitor, 'visa_master');

        $request->session()->flash(
            'status',
            'Your existing unpaid registration was found. Continue securely from the payment step.'
        );

        return route('visitor.payment.card');
    }

    /** Restore an existing paid registration and return its entrance-card URL. */
    public function resumePaid(Request $request, VerifiedVisitor $visitor): string
    {
        $visitor->loadMissing(['eventRegistrationDay', 'visitorCategory']);
        $this->restoreSession($request, $visitor, $visitor->payment_method);

        return route('visitor.thank-you');
    }

    private function restoreSession(Request $request, VerifiedVisitor $visitor, ?string $paymentMethod): void
    {
        $day = $visitor->eventRegistrationDay;
        $category = $visitor->visitorCategory;
        $request->session()->forget(['verification', 'didit_verification']);
        $request->session()->put('visitor_registration', [
            'record_id' => $visitor->id,
            'verification_id' => $visitor->verification_id,
            'document_type' => $visitor->document_type,
            'document_number' => $visitor->document_number,
            'full_name' => $visitor->full_name,
            'full_name_latin' => $visitor->full_name_latin,
            'email' => $visitor->email,
            'address' => $visitor->address,
            'address_latin' => $visitor->address_latin,
            'mobile_number' => $visitor->mobile_number,
            'whatsapp_number' => $visitor->whatsapp_number,
            'occupation' => $visitor->occupation,
            'company' => $visitor->company,
            'category' => $visitor->category,
            'visitor_category_id' => $visitor->visitor_category_id,
            'entrance_fee' => $visitor->entrance_fee,
            'event_registration_day_id' => $day?->id,
            'registration_day_label' => $day?->label,
            'registration_date' => $day?->event_date?->format('Y-m-d'),
            'photo_path' => $visitor->photo_path,
            'photo_mime' => $visitor->photo_mime,
            'back_photo_path' => $visitor->back_photo_path,
            'back_photo_mime' => $visitor->back_photo_mime,
            'selfie_path' => $visitor->selfie_path,
            'selfie_mime' => $visitor->selfie_mime,
            'payment_method' => $paymentMethod,
            'payment_status' => $visitor->payment_status,
        ]);

        if ($day) {
            $request->session()->put('event_registration_day', [
                'id' => $day->id,
                'label' => $day->label,
                'event_date' => $day->event_date->format('Y-m-d'),
                'entrance_fee' => $visitor->entrance_fee,
            ]);
        } else {
            $request->session()->forget('event_registration_day');
        }
        if ($category) {
            $request->session()->put('visitor_category', [
                'id' => $category->id,
                'name' => $category->name,
                'entrance_fee' => $visitor->entrance_fee,
            ]);
        } else {
            $request->session()->forget('visitor_category');
        }
    }

    public function normalizeNic(string $documentNumber): string
    {
        return strtoupper((string) preg_replace('/[^0-9VX]/', '', trim($documentNumber)));
    }
}
