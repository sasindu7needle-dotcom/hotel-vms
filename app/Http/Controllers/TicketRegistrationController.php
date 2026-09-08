<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use App\Services\VisitorMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class TicketRegistrationController extends Controller
{
    /** Display the standalone ticket-holder registration form. */
    public function create(): View
    {
        return view('ticket-registration.create');
    }

    /** Register a ticket holder without entering the identity/payment flows. */
    public function store(Request $request, VisitorMediaService $media): RedirectResponse
    {
        $request->merge([
            'ticket_number' => Str::upper(trim((string) $request->input('ticket_number'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'designation' => ['required', 'string', 'max:100'],
            'company' => ['required', 'string', 'max:150'],
            'whatsapp_number' => ['required', 'regex:/^(?:\+94|94|0)?7\d{8}$/'],
            'email' => ['required', 'email:rfc', 'max:100'],
            'ticket_number' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z0-9][A-Z0-9\/_\-.]*$/',
                Rule::unique('verified_visitors', 'ticket_number'),
            ],
            'profile_image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ], [
            'whatsapp_number.regex' => 'Enter a valid Sri Lankan WhatsApp number.',
            'ticket_number.regex' => 'The ticket number may contain letters, numbers, hyphens, underscores, dots, and slashes only.',
            'ticket_number.unique' => 'This ticket number has already been registered.',
        ]);

        $verificationId = (string) Str::uuid();
        $image = $request->file('profile_image');
        $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
        $photoPath = $media->storeAs(
            $image,
            'verified-visitors',
            $verificationId.'-ticket-profile.'.$extension,
        );

        if (! $photoPath) {
            throw new RuntimeException('The profile image could not be stored.');
        }

        $whatsappNumber = $this->normaliseSriLankanPhone($validated['whatsapp_number']);
        $visitor = VerifiedVisitor::create([
            'verification_id' => $verificationId,
            'full_name' => trim($validated['name']),
            'full_name_latin' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'mobile_number' => $whatsappNumber,
            'whatsapp_number' => $whatsappNumber,
            'occupation' => trim($validated['designation']),
            'company' => trim($validated['company']),
            'ticket_number' => $validated['ticket_number'],
            'selfie_path' => $photoPath,
            'selfie_mime' => $image->getMimeType() ?: 'image/jpeg',
            'category' => 'Ticket Holder',
            'entrance_fee' => 0,
            'payment_status' => 'pending',
            'registration_status' => 'payment_pending',
            'identity_reviewed_at' => now(),
            'verified_at' => now(),
            'ocr_provider' => 'ticket_registration',
        ]);

        $request->session()->put('visitor_registration', [
            'record_id' => $visitor->id,
            'verification_id' => $visitor->verification_id,
            'full_name' => $visitor->full_name,
            'email' => $visitor->email,
            'whatsapp_number' => $visitor->whatsapp_number,
            'occupation' => $visitor->occupation,
            'company' => $visitor->company,
            'ticket_number' => $visitor->ticket_number,
            'category' => $visitor->category,
            'selfie_path' => $visitor->selfie_path,
            'selfie_mime' => $visitor->selfie_mime,
            'payment_status' => 'pending',
            'manual_registration' => true,
            'ticket_registration' => true,
        ]);

        return redirect()->route('visitor.thank-you');
    }

    private function normaliseSriLankanPhone(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if (str_starts_with($digits, '0')) {
            $digits = '94'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '94')) {
            $digits = '94'.$digits;
        }

        return '+'.$digits;
    }
}
