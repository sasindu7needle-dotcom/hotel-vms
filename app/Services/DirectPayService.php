<?php

namespace App\Services;

use App\Models\DirectPayPayment;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class DirectPayService
{
    public function isConfigured(): bool
    {
        return config('services.directpay.environment') === 'sandbox'
            && filled($this->merchantId())
            && filled($this->merchantSecret())
            && $this->currency() === 'LKR';
    }

    public function merchantId(): string
    {
        return trim((string) config('services.directpay.merchant_id', ''));
    }

    public function currency(): string
    {
        return strtoupper((string) config('services.directpay.currency', 'LKR'));
    }

    public function generateReference(int $visitorId): string
    {
        do {
            $reference = sprintf('DP-V%d-%s', $visitorId, strtoupper(Str::random(8)));
            $reference = substr($reference, 0, 20);
        } while (DirectPayPayment::where('reference', $reference)->exists());

        return $reference;
    }

    /** @return array{signature: string, dataString: string, stage: string, container: string} */
    public function checkoutConfiguration(DirectPayPayment $payment): array
    {
        [$firstName, $lastName] = $this->splitName((string) $payment->visitor->full_name);

        $payload = [
            'merchant_id' => $this->merchantId(),
            'amount' => number_format((float) $payment->expected_amount, 2, '.', ''),
            'type' => 'ONE_TIME',
            'order_id' => $payment->reference,
            'currency' => $payment->currency,
            'return_url' => route('visitor.payment.directpay.status', $payment->reference),
            'response_url' => route('directpay.confirmation'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => (string) $payment->visitor->email,
            'phone' => $this->localPhone((string) $payment->visitor->mobile_number),
            'logo' => secure_asset('img/logo.png'),
        ];

        try {
            $dataString = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The DirectPay payment payload could not be encoded.', previous: $exception);
        }

        return [
            'signature' => hash_hmac('sha256', $dataString, $this->merchantSecret()),
            'dataString' => $dataString,
            'stage' => 'DEV',
            'container' => 'card_container',
        ];
    }

    public function verifyCallbackSignature(string $requestBody, ?string $authorization): bool
    {
        $parts = preg_split('/\s+/', trim((string) $authorization));
        if (! is_array($parts) || count($parts) !== 2 || $parts[1] === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $requestBody, $this->merchantSecret());

        return hash_equals($expected, strtolower($parts[1]));
    }

    /** @return array<string, mixed> */
    public function decodeCallback(string $requestBody): array
    {
        $decoded = base64_decode(trim($requestBody), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('The DirectPay callback is not valid base64.');
        }

        try {
            $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The DirectPay callback does not contain valid JSON.', previous: $exception);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('The DirectPay callback payload is invalid.');
        }

        return $payload;
    }

    public function normalizeStatus(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'SUCCESS' => 'paid',
            'FAILED', 'FAILURE', 'DECLINED', 'ERROR' => 'failed',
            'CANCELLED', 'CANCELED' => 'cancelled',
            default => 'pending',
        };
    }

    public function amountsMatch(string|int|float|null $expected, string|int|float|null $actual): bool
    {
        if (! is_numeric($expected) || ! is_numeric($actual)) {
            return false;
        }

        return (int) round((float) $expected * 100) === (int) round((float) $actual * 100);
    }

    private function merchantSecret(): string
    {
        return trim((string) config('services.directpay.merchant_secret', ''));
    }

    /** @return array{string, string} */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function localPhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '94')) {
            return '0'.substr($digits, 2);
        }

        return str_starts_with($digits, '0') ? $digits : '0'.$digits;
    }
}
