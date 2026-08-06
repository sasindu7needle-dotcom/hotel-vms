<?php

namespace App\Http\Controllers;

use App\Models\VerifiedVisitor;
use App\Exceptions\GateScanException;
use App\Models\GateLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\GateLogService;
use F9WebLtd\QrCode\Facades\QrCode;

class AdminVisitorController extends Controller
{
    public function index(Request $request, GateLogService $gateLogService)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'payment_status' => 'nullable|in:pending,paid',
            'checkin_status' => 'nullable|in:inside,outside',
        ]);

        $latestGateLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');

        $visitors = VerifiedVisitor::query()
            ->with(['gateLogs' => fn ($query) => $query->orderBy('scanned_at')->orderBy('id')])
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('full_name_latin', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'payment_status') === 'pending', fn ($query) => $query->whereIn('payment_status', ['pending', 'cash_pending', 'card_pending']))
            ->when(data_get($filters, 'payment_status') === 'paid', fn ($query) => $query->where('payment_status', 'paid'))
            ->when(data_get($filters, 'checkin_status') === 'inside', fn ($query) => $query->whereHas('gateLogs', fn ($logs) => $logs->whereIn('id', $latestGateLogIds)->where('direction', 'in')))
            ->when(data_get($filters, 'checkin_status') === 'outside', fn ($query) => $query->whereDoesntHave('gateLogs', fn ($logs) => $logs->whereIn('id', $latestGateLogIds)->where('direction', 'in')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $visitors->getCollection()->each(function ($visitor) use ($gateLogService) {
            $visitor->setAttribute('activity_rows', $gateLogService->activityRows($visitor->gateLogs));
        });

        $stats = [
            'total' => VerifiedVisitor::count(),
            'verified_today' => VerifiedVisitor::whereDate('verified_at', today())->count(),
            'inside' => VerifiedVisitor::whereHas('gateLogs', fn ($logs) => $logs->whereIn('id', $latestGateLogIds)->where('direction', 'in'))->count(),
            'payment_pending' => VerifiedVisitor::whereIn('payment_status', ['pending', 'cash_pending', 'card_pending'])->count(),
        ];

        return view('admin.visitors.index', compact('visitors', 'stats', 'filters'));
    }

    public function toggleCheckin(VerifiedVisitor $visitor, GateLogService $gateLogService)
    {
        try {
            $adminUsername = (string) request()->session()->get('admin_username');
            $scannedBy = auth()->id() ?: User::query()
                ->where('name', $adminUsername)
                ->orWhere('email', $adminUsername)
                ->value('id');
            $log = $gateLogService->scan((string) ($visitor->verification_id ?: $visitor->id), 'ADMIN', $scannedBy);
        } catch (GateScanException $exception) {
            return redirect()->route('admin.visitors.index')->withErrors(['checkin' => $exception->getMessage()]);
        }

        return redirect()->route('admin.visitors.index')->with('status', 'Visitor checked '.strtoupper($log->direction).' from the admin control.');
    }

    public function update(Request $request, VerifiedVisitor $visitor)
    {
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:180',
            'document_type' => 'nullable|in:nic,driving_license,passport',
            'document_number' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'mobile_number' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'occupation' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:100',
            'entrance_fee' => 'nullable|numeric|min:0|max:9999999999',
            'payment_method' => 'nullable|in:visa_master,amex,cash',
            'payment_status' => 'required|in:pending,paid',
            'is_blocked' => 'required|boolean',
        ]);

        if (! empty($validated['document_number'])) {
            $validated['document_number'] = strtoupper(preg_replace('/\s+/', '', $validated['document_number']));
        }
        $validated['full_name_latin'] = $validated['full_name'] ?? null;
        $validated['address_latin'] = $validated['address'] ?? null;
        $visitor->update($validated);

        return redirect()->route('admin.visitors.index')->with('status', 'Visitor details updated successfully.');
    }

    public function destroy(VerifiedVisitor $visitor)
    {
        $paths = collect([
            $visitor->photo_path,
            $visitor->back_photo_path,
            $visitor->selfie_path,
        ])
        ->filter()
        ->map(fn ($path) => str_replace('\\', '/', trim($path)));

        // Include any related files left by earlier registration attempts. Restrict
        // matches to a complete identifier prefix so visitor 1 cannot match visitor 10.
        $searchIds = array_filter([$visitor->verification_id, (string) $visitor->id]);
        foreach (['local', 'public'] as $diskName) {
            foreach (Storage::disk($diskName)->allFiles('verified-visitors') as $file) {
                $normalized = str_replace('\\', '/', $file);
                $filename = basename($normalized);
                $belongsToVisitor = collect($searchIds)->contains(
                    fn ($searchId) => preg_match(
                        '/^'.preg_quote((string) $searchId, '/').'(?:[._-]|$)/',
                        $filename
                    ) === 1
                );

                if ($belongsToVisitor) {
                    $paths->push($normalized);
                }
            }
        }

        $validPaths = $paths
            ->filter()
            ->map(fn ($path) => str_replace('\\', '/', trim($path)))
            ->filter(fn ($path) => str_starts_with($path, 'verified-visitors/') && ! str_contains($path, '..'))
            ->unique()
            ->values();

        $failedDeletes = collect();
        foreach ($validPaths as $path) {
            foreach (['local', 'public'] as $diskName) {
                $disk = Storage::disk($diskName);

                try {
                    if ($disk->exists($path) && (! $disk->delete($path) || $disk->exists($path))) {
                        $failedDeletes->push("{$diskName}:{$path}");
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                    $failedDeletes->push("{$diskName}:{$path}");
                }
            }
        }

        if ($failedDeletes->isNotEmpty()) {
            return redirect()
                ->route('admin.visitors.index')
                ->withErrors([
                    'delete' => 'The visitor was not deleted because one or more identity photos could not be removed. Please retry or check storage permissions.',
                ]);
        }

        // Clean up legacy Visitor records matching contact/email if present
        if ($visitor->email || $visitor->phone) {
            \App\Models\Visitor::query()
                ->when($visitor->email, fn ($q) => $q->where('email', $visitor->email))
                ->when($visitor->phone, fn ($q) => $q->orWhere('phone', $visitor->phone))
                ->delete();
        }

        $visitor->gateLogs()->delete();
        $visitor->delete();

        return redirect()->route('admin.visitors.index')->with('status', 'Visitor record and all associated identity & document photos deleted successfully.');
    }

    public function photo(VerifiedVisitor $visitor)
    {
        abort_unless($visitor->photo_path && Storage::disk('local')->exists($visitor->photo_path), 404);

        return $this->currentPrivateImage($visitor->photo_path, $visitor->photo_mime);
    }

    public function badge(VerifiedVisitor $visitor)
    {
        if (blank($visitor->selfie_path)
            || ! Storage::disk('local')->exists($visitor->selfie_path)) {
            return redirect()
                ->route('admin.visitors.index')
                ->withErrors([
                    'badge' => 'This card cannot be printed until a visitor photo has been captured.',
                ]);
        }

        $qrPayload = (string) ($visitor->verification_id ?: $visitor->id);
        $qrCode = QrCode::format('svg')
            ->size(260)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrPayload);

        return view('admin.visitors.badge', [
            'visitor' => $visitor,
            'eventName' => config('vms.event_name'),
            'qrPayload' => $qrPayload,
            'qrCode' => $qrCode,
        ]);
    }

    public function selfie(VerifiedVisitor $visitor)
    {
        abort_unless($visitor->selfie_path && Storage::disk('local')->exists($visitor->selfie_path), 404);

        return $this->currentPrivateImage($visitor->selfie_path, $visitor->selfie_mime);
    }

    public function backPhoto(VerifiedVisitor $visitor)
    {
        abort_unless($visitor->back_photo_path && Storage::disk('local')->exists($visitor->back_photo_path), 404);

        return $this->currentPrivateImage($visitor->back_photo_path, $visitor->back_photo_mime);
    }

    private function currentPrivateImage(string $path, ?string $mime)
    {
        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime ?: 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
