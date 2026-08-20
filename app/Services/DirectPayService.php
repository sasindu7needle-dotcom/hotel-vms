<?php

namespace App\Services;

use App\Models\DirectPayPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DirectPayService
{
    public function isConfigured(): bool
    {
        return config('services.directpay.environment') === 'sandbox'
            && filled($this->merchantId())
            && filled($this->apiKey())
            && $this->currency() === 'LKR'
            && (filled(config('services.directpay.merchant_secret'))
                || filled(config('services.directpay.private_key_path')));
    }

    public function merchantId(): string
    {
        return (string) config('services.directpay.merchant_id', '');
    }

    public function apiKey(): string
    {
        return (string) config('services.directpay.api_key', '');
    }

    public function currency(): string
    {
        return strtoupper((string) config('services.directpay.currency', 'LKR'));
    }

    public function generateReference(int $visitorId): string
    {
        do {
            // DirectPay documents a maximum refCode length of 20 characters.
            $reference = sprintf('DP-V%d-%s', $visitorId, strtoupper(Str::random(8)));
            $reference = substr($reference, 0, 20);
        } while (DirectPayPayment::where('reference', $reference)->exists());

        return $reference;
    }

    public function checkoutConfiguration(DirectPayPayment $payment): array
    {
        return [
            'merchantId' => $this->merchantId(),
            'amount' => number_format((float) $payment->expected_amount, 2, '.', ''),
            'refCode' => $payment->reference,
            'currency' => $payment->currency,
            'type' => 'ONE_TIME_PAYMENT',
            'customerEmail' => (string) $payment->visitor->email,
            'customerMobile' => (string) $payment->visitor->mobile_number,
            'description' => 'Visitor entrance fee '.$payment->reference,
            'debug' => config('services.directpay.environment') === 'sandbox',
            'apiKey' => $this->apiKey(),
        ];
    }

    /**
     * Verify a callback against DirectPay's signed server-side status endpoint.
     *
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $transactionId, string $reference): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('DirectPay credentials are not configured.');
        }

        $body = [
            'merchantId' => $this->merchantId(),
            'type' => 'TRANSACTION_STATUS',
            'transactionId' => $transactionId,
            'merchantReference' => $reference,
        ];

        // DirectPay requires field values to be concatenated in request-body order.
        $signature = $this->sign(implode('', array_values($body)));
        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.directpay.timeout', 15))
            ->withHeaders([
                'Signature' => $signature,
                'x-api-key' => $this->apiKey(),
            ])
            ->post((string) config('services.directpay.status_url'), $body);

        if (! $response->successful()) {
            throw new RuntimeException('DirectPay status verification was unavailable.');
        }

        $payload = $response->json();
        if (! is_array($payload) || (int) data_get($payload, 'status') !== 200) {
            throw new RuntimeException('DirectPay rejected the status verification request.');
        }

        $transactions = data_get($payload, 'data');
        if (! is_array($transactions)) {
            throw new RuntimeException('DirectPay returned an invalid status response.');
        }

        if (! array_is_list($transactions)) {
            $transactions = [$transactions];
        }

        foreach ($transactions as $transaction) {
            if (is_array($transaction)
                && hash_equals((string) data_get($transaction, 'transactionId'), $transactionId)) {
                return $transaction;
            }
        }

        throw new RuntimeException('DirectPay did not confirm the requested transaction.');
    }

    public function normalizeStatus(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'SUCCESS' => 'paid',
            'FAILED', 'DECLINED' => 'failed',
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

    private function sign(string $data): string
    {
        $key = $this->privateKey();
        $privateKey = @openssl_pkey_get_private($key);
        if ($privateKey === false) {
            throw new RuntimeException('The DirectPay merchant secret is not a valid RSA private key.');
        }

        $signed = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new RuntimeException('The DirectPay verification request could not be signed.');
        }

        return base64_encode($signature);
    }

    private function privateKey(): string
    {
        $path = (string) config('services.directpay.private_key_path', '');
        if ($path !== '') {
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('The DirectPay private key file is not readable.');
            }

            return (string) file_get_contents($path);
        }

        $secret = str_replace('\\n', "\n", trim((string) config('services.directpay.merchant_secret', '')));
        if (str_contains($secret, 'BEGIN') && str_contains($secret, 'PRIVATE KEY')) {
            return $secret;
        }

        $decoded = base64_decode($secret, true);
        if ($decoded !== false && str_contains($decoded, 'PRIVATE KEY')) {
            return $decoded;
        }

        return $secret;
    }
}
