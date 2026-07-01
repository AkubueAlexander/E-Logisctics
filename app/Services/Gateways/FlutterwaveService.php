<?php

namespace App\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FlutterwaveService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
        $this->baseUrl = config('services.flutterwave.base_url');
    }

    public function resolveBankAccount(string $accountNumber, string $bankCode): string
    {
        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->post("{$this->baseUrl}/accounts/resolve", [
                'account_number' => $accountNumber,
                'account_bank'   => $bankCode,
            ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RuntimeException($response->json('message') ?? 'Could not resolve bank account details.');
        }

        return $response->json('data.account_name');
    }

    /**
     * Trigger an external disbursement via Flutterwave Transfers API.
     */
    public function createTransfer(string $reference, int $amountMinor, string $bankCode, string $accountNumber, string $narration): array
    {
        // Flutterwave expects standard currency units (Naira, float), so we convert from minor units (Kobo)
        $amountMainUnit = $amountMinor / 100;

        $response = Http::withToken($this->secretKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/transfers", [
                'account_bank'   => $bankCode,
                'account_number' => $accountNumber,
                'amount'         => $amountMainUnit,
                'narration'      => $narration,
                'currency'       => 'NGN',
                'reference'      => $reference,
                'callback_url'   => route('webhooks.flutterwave.transfers'), // We will build this next
            ]);

        if ($response->failed()) {
            Log::error('Flutterwave Transfer Connection Error', [
                'reference' => $reference,
                'response'  => $response->body()
            ]);
            throw new RuntimeException('Failed to communicate with payout gateway.');
        }

        $body = $response->json();

        if ($body['status'] !== 'success' && ($body['message'] ?? '') !== 'Transfer queued successfully') {
            Log::error('Flutterwave Transfer Rejected', [
                'reference' => $reference,
                'response'  => $body
            ]);
            throw new RuntimeException($body['message'] ?? 'Transfer initialization rejected by gateway.');
        }

        return $body['data'];
    }
}
