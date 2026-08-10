<?php

namespace App\Http\Controllers;

use App\Exceptions\GateScanException;
use App\Models\User;
use App\Models\VerifiedVisitor;
use App\Services\GateLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class GateTerminalController extends Controller
{
    public function show(string $direction)
    {
        abort_unless(in_array($direction, ['in', 'out'], true), 404);

        return view('gate.scanner', [
            'gate' => 'A',
            'direction' => $direction,
        ]);
    }

    public function scan(Request $request, string $direction, GateLogService $service): JsonResponse
    {
        abort_unless(in_array($direction, ['in', 'out'], true), 404);

        $validated = $request->validate([
            'qr_value' => ['required', 'string', 'max:1000'],
            'gate' => ['nullable', 'string', 'max:30', Rule::in(['A'])],
            'direction' => ['nullable', Rule::in([$direction])],
            'action' => ['nullable', Rule::in(['preview', 'accept'])],
        ]);

        try {
            if (($validated['action'] ?? 'accept') === 'preview') {
                $visitor = $service->preview($validated['qr_value'], $direction);

                return response()->json([
                    'ok' => true,
                    'requires_confirmation' => true,
                    'message' => 'Registration found. Compare the visitor with this card.',
                    'visitor' => $this->visitorCard($visitor),
                ]);
            }

            $adminUsername = (string) $request->session()->get('admin_username');
            $scannedBy = auth()->id() ?: User::query()
                ->where('name', $adminUsername)
                ->orWhere('email', $adminUsername)
                ->value('id');

            $log = $service->scan($validated['qr_value'], 'A', $scannedBy, $direction);
        } catch (GateScanException $exception) {
            return response()->json([
                'ok' => false,
                'reason' => $exception->reason,
                'message' => $exception->getMessage(),
            ], $exception->status);
        }

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Accepted — checked %s at %s, Gate %s.',
                strtoupper($log->direction),
                $log->scanned_at->format('H:i'),
                $log->gate
            ),
            'visitor' => $this->visitorCard($log->visitor),
            'movement' => [
                'direction' => $log->direction,
                'gate' => $log->gate,
                'scanned_at' => $log->scanned_at->toIso8601String(),
                'display_time' => $log->scanned_at->format('H:i'),
            ],
        ]);
    }

    public function photo(VerifiedVisitor $visitor): Response
    {
        $path = $visitor->selfie_path ?: $visitor->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mime = $visitor->selfie_path ? $visitor->selfie_mime : $visitor->photo_mime;

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function visitorCard(VerifiedVisitor $visitor): array
    {
        $hasPrivatePhoto = (bool) ($visitor->selfie_path ?: $visitor->photo_path);

        return [
            'name' => $visitor->full_name ?: 'Unnamed visitor',
            'photo_url' => $hasPrivatePhoto
                ? URL::temporarySignedRoute('gate.photo', now()->addMinutes(5), ['visitor' => $visitor])
                : $visitor->photo_url,
            'category' => $visitor->category ?: 'Visitor',
            'document_type' => strtoupper(str_replace('_', ' ', $visitor->document_type ?: 'Identity document')),
            'document_number' => $visitor->document_number ?: 'Not provided',
            'company' => $visitor->company ?: ($visitor->occupation ?: 'Not provided'),
            'reference' => $visitor->verification_id ?: (string) $visitor->id,
            'event_day' => $visitor->eventRegistrationDay?->event_date?->format('d M Y'),
        ];
    }
}
