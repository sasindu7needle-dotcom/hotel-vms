<?php

namespace App\Services;

use App\Exceptions\GateScanException;
use App\Models\EventConfiguration;
use App\Models\GateLog;
use App\Models\VerifiedVisitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GateLogService
{
    private const REPLAY_WINDOW_SECONDS = 5;

    public function preview(string $payload, ?string $requestedDirection = null): VerifiedVisitor
    {
        $visitor = $this->findVisitor($this->extractIdentifier($payload));
        $latest = GateLog::query()
            ->where('visitor_id', $visitor->id)
            ->latest('scanned_at')
            ->latest('id')
            ->first();

        $this->assertVisitorCanMove($visitor, $latest, $requestedDirection);

        return $visitor;
    }

    public function scan(string $payload, string $gate, ?int $scannedBy = null, ?string $requestedDirection = null): GateLog
    {
        $identifier = $this->extractIdentifier($payload);

        return DB::transaction(function () use ($identifier, $gate, $scannedBy, $requestedDirection) {
            $visitor = VerifiedVisitor::query()
                ->where(function ($query) use ($identifier) {
                    $query->where('verification_id', $identifier);

                    if (ctype_digit($identifier)) {
                        $query->orWhereKey((int) $identifier);
                    }
                })
                ->lockForUpdate()
                ->first();

            $this->assertVisitorExists($visitor);

            $latest = GateLog::query()
                ->where('visitor_id', $visitor->id)
                ->latest('scanned_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $this->assertVisitorCanMove($visitor, $latest, $requestedDirection, true);

            if ($requestedDirection === null && $latest && $latest->scanned_at->diffInSeconds(now()) < self::REPLAY_WINDOW_SECONDS) {
                throw new GateScanException(
                    'Duplicate scan ignored. Wait a moment, then scan again for the next movement.',
                    'duplicate'
                );
            }

            $direction = $requestedDirection ?: ($latest?->direction === 'in' ? 'out' : 'in');
            $scannedAt = now();

            $log = GateLog::create([
                'visitor_id' => $visitor->id,
                'gate' => strtoupper($gate),
                'direction' => $direction,
                'scanned_at' => $scannedAt,
                'scanned_by' => $scannedBy,
            ]);

            $visitor->update([
                'checkin_status' => $direction === 'in',
                'checked_in_at' => $direction === 'in' ? $scannedAt : $visitor->checked_in_at,
                'checked_out_at' => $direction === 'out' ? $scannedAt : null,
                'registration_status' => $direction === 'in' ? 'checked_in' : 'checked_out',
            ]);

            return $log->setRelation('visitor', $visitor);
        }, 3);
    }

    public function activityRows(Collection $logs): Collection
    {
        $rows = collect();
        $open = null;

        foreach ($logs->sortBy([['scanned_at', 'asc'], ['id', 'asc']]) as $log) {
            if ($log->direction === 'in') {
                $open = $log;
                continue;
            }

            if ($open) {
                $rows->push([
                    'date' => $open->scanned_at->toDateString(),
                    'in' => $open,
                    'out' => $log,
                    'duration_minutes' => (int) $open->scanned_at->diffInMinutes($log->scanned_at),
                ]);
                $open = null;
            }
        }

        if ($open) {
            $rows->push([
                'date' => $open->scanned_at->toDateString(),
                'in' => $open,
                'out' => null,
                'duration_minutes' => null,
            ]);
        }

        return $rows->sortByDesc(fn ($row) => $row['in']->scanned_at)->values();
    }

    private function findVisitor(string $identifier): VerifiedVisitor
    {
        $visitor = VerifiedVisitor::query()
            ->where(function ($query) use ($identifier) {
                $query->where('verification_id', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhereKey((int) $identifier);
                }
            })
            ->first();

        $this->assertVisitorExists($visitor);

        return $visitor;
    }

    private function assertVisitorExists(?VerifiedVisitor $visitor): void
    {
        if (! $visitor) {
            throw new GateScanException('Invalid or unrecognized visitor QR code.', 'not_found', 404);
        }
    }

    private function assertVisitorCanMove(
        VerifiedVisitor $visitor,
        ?GateLog $latest,
        ?string $requestedDirection,
        bool $lockCapacity = false
    ): void
    {
        if ($visitor->is_blocked) {
            throw new GateScanException('Access denied — this visitor is blocked.', 'blocked', 403);
        }

        $direction = $latest?->direction === 'in' ? 'out' : 'in';

        if ($requestedDirection !== null && ! in_array($requestedDirection, ['in', 'out'], true)) {
            throw new GateScanException('Invalid gate movement direction.', 'invalid_direction');
        }

        if ($requestedDirection !== null && $requestedDirection !== $direction) {
            if ($requestedDirection === 'in') {
                throw new GateScanException(
                    'Duplicate check-in rejected — this visitor is already inside.',
                    'duplicate_in'
                );
            }

            throw new GateScanException(
                'Check-out rejected — this visitor has no active check-in.',
                'out_without_in'
            );
        }

        if ($direction === 'in') {
            $configurationQuery = EventConfiguration::query()
                ->where('singleton_key', EventConfiguration::SINGLETON_KEY);
            if ($lockCapacity) {
                $configurationQuery->lockForUpdate();
            }

            $capacityLimit = $configurationQuery->value('capacity_limit');
            if ($capacityLimit !== null) {
                $latestLogIds = GateLog::query()->selectRaw('MAX(id)')->groupBy('visitor_id');
                $insideCount = VerifiedVisitor::query()
                    ->whereHas('gateLogs', fn ($query) => $query
                        ->whereIn('id', $latestLogIds)
                        ->where('direction', 'in'))
                    ->count();

                if ($insideCount >= (int) $capacityLimit) {
                    throw new GateScanException(
                        "Check-in rejected — the event capacity of {$capacityLimit} is full.",
                        'capacity_reached',
                        409
                    );
                }
            }
        }
    }

    private function extractIdentifier(string $payload): string
    {
        $payload = trim($payload);
        $decoded = json_decode($payload, true);

        if (is_array($decoded)) {
            $payload = (string) ($decoded['visitor_id'] ?? $decoded['verification_id'] ?? $decoded['payment_reference'] ?? '');
        } elseif (filter_var($payload, FILTER_VALIDATE_URL)) {
            parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);
            $payload = (string) ($query['visitor_id'] ?? $query['verification_id'] ?? $query['payment_reference'] ?? basename(parse_url($payload, PHP_URL_PATH)));
        }

        $identifier = trim($payload);

        if ($identifier === '' || mb_strlen($identifier) > 255) {
            throw new GateScanException('Invalid or unrecognized visitor QR code.', 'invalid_payload', 422);
        }

        return $identifier;
    }
}
