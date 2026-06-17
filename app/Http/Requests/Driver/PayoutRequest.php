<?php

namespace App\Http\Requests\Driver;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

class PayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Governed by auth:sanctum and driver role middleware
    }

    public function rules(): array
    {
        return [
            'amount_minor_unit' => [
                'required',
                'integer',
                'min:10000', // Minimum payout threshold (e.g., 100 NGN)
                function ($attribute, $value, $fail) {
                    $driverId = $this->user()->id;

                    // Calculate live withdrawable balance from ledger
                    $credits = Ledger::where('user_id', $driverId)->where('status', 'cleared')->where('transaction_type', 'driver_payout')->sum('amount_minor_unit');
                    $debits = Ledger::where('user_id', $driverId)->where('transaction_type', 'driver_withdrawal')->sum('amount_minor_unit');
                    $availableBalance = $credits - $debits;

                    if ($value > $availableBalance) {
                        $fail('Insufficient withdrawable funds in your wallet ledger.');
                    }
                }
            ],
            'bank_code' => ['required', 'string'],
            'account_number' => ['required', 'string', 'digits:10'],
        ];
    }
}
