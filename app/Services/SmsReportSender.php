<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsReportSender
{
    public function send(string $recipient, string $message): void
    {
        $url = config('services.sms.url');
        if (! $url) {
            throw new RuntimeException('SMS delivery is not configured. Set SMS_API_URL before enabling an SMS schedule.');
        }

        $request = Http::acceptJson()->asJson()->timeout(15);
        if ($token = config('services.sms.token')) {
            $request = $request->withToken($token);
        }

        $payload = [
            config('services.sms.recipient_field', 'to') => $recipient,
            config('services.sms.message_field', 'message') => $message,
        ];
        if ($sender = config('services.sms.sender')) {
            $payload[config('services.sms.sender_field', 'from')] = $sender;
        }
        $response = $request->post($url, $payload);

        $response->throw();
    }
}
