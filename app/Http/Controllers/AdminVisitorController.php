<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminVisitorController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'payment_status' => 'nullable|in:pending,cash_pending,card_pending,paid',
            'checkin_status' => 'nullable|in:inside,outside',
        ]);

        $visitors = VerifiedVisitor::query()
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('full_name_latin', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'payment_status'), fn ($query, $status) => $query->where('payment_status', $status))
            ->when(data_get($filters, 'checkin_status') === 'inside', fn ($query) => $query->where('checkin_status', true))
            ->when(data_get($filters, 'checkin_status') === 'outside', fn ($query) => $query->where('checkin_status', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => VerifiedVisitor::count(),
            'verified_today' => VerifiedVisitor::whereDate('verified_at', today())->count(),
            'inside' => VerifiedVisitor::where('checkin_status', true)->count(),
            'payment_pending' => VerifiedVisitor::whereIn('payment_status', ['pending', 'cash_pending', 'card_pending'])->count(),
        ];

        return view('admin.visitors.index', compact('visitors', 'stats', 'filters'));
    }

    public function toggleCheckin(VerifiedVisitor $visitor)
    {
        $checkingIn = ! $visitor->checkin_status;
        $visitor->update([
            'checkin_status' => $checkingIn,
            'checked_in_at' => $checkingIn ? now() : $visitor->checked_in_at,
            'checked_out_at' => $checkingIn ? null : now(),
            'registration_status' => $checkingIn ? 'checked_in' : 'checked_out',
        ]);

        return back()->with('status', $checkingIn ? 'Visitor checked in.' : 'Visitor checked out.');
    }

    public function update(Request $request, VerifiedVisitor $visitor)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:180',
            'document_type' => 'required|in:nic,driving_license,passport',
            'document_number' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'mobile_number' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'occupation' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:100',
            'entrance_fee' => 'nullable|numeric|min:0|max:9999999999',
            'payment_method' => 'nullable|in:visa_master,amex,cash',
            'payment_status' => 'required|in:pending,cash_pending,card_pending,paid',
            'face_verification_status' => 'required|in:pending,verified,review_required,rejected',
            'checkin_status' => 'required|boolean',
        ]);

        $validated['document_number'] = strtoupper(preg_replace('/\s+/', '', $validated['document_number']));
        $validated['full_name_latin'] = $validated['full_name'];
        $validated['address_latin'] = $validated['address'];
        $validated['face_verified_at'] = $validated['face_verification_status'] === 'verified'
            ? ($visitor->face_verified_at ?: now())
            : null;

        $checkingIn = (bool) $validated['checkin_status'];
        $validated['checked_in_at'] = $checkingIn ? ($visitor->checked_in_at ?: now()) : $visitor->checked_in_at;
        $validated['checked_out_at'] = $checkingIn ? null : ($visitor->checkin_status ? now() : $visitor->checked_out_at);
        $validated['registration_status'] = $checkingIn ? 'checked_in' : 'checked_out';

        $visitor->update($validated);

        return back()->with('status', 'Visitor details updated successfully.');
    }

    public function destroy(VerifiedVisitor $visitor)
    {
        $paths = collect([$visitor->photo_path, $visitor->back_photo_path, $visitor->selfie_path])
            ->filter()
            ->map(fn ($path) => str_replace('\\', '/', trim($path)))
            ->filter(fn ($path) => str_starts_with($path, 'verified-visitors/') && ! str_contains($path, '..'))
            ->unique()
            ->values();

        foreach ($paths as $path) {
            if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                logger()->error('Visitor deletion stopped because a private image could not be removed.', [
                    'visitor_id' => $visitor->id,
                ]);

                return back()->withErrors([
                    'delete' => 'The visitor was not deleted because one of the private images could not be removed safely.',
                ]);
            }
        }

        $visitor->delete();

        return redirect()->route('admin.visitors.index')->with('status', 'Visitor record and private images deleted.');
    }

    public function photo(VerifiedVisitor $visitor)
    {
        abort_unless($visitor->photo_path && Storage::disk('local')->exists($visitor->photo_path), 404);

        return Storage::disk('local')->response($visitor->photo_path, null, [
            'Content-Type' => $visitor->photo_mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function selfie(VerifiedVisitor $visitor)
    {
        abort_unless($visitor->selfie_path && Storage::disk('local')->exists($visitor->selfie_path), 404);

        return Storage::disk('local')->response($visitor->selfie_path, null, [
            'Content-Type' => $visitor->selfie_mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function backPhoto(VerifiedVisitor $visitor)
    {
        abort_unless($visitor->back_photo_path && Storage::disk('local')->exists($visitor->back_photo_path), 404);

        return Storage::disk('local')->response($visitor->back_photo_path, null, [
            'Content-Type' => $visitor->back_photo_mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
