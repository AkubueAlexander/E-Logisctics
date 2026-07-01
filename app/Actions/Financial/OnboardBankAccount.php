<?php

namespace App\Actions\Financial;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\Gateways\FlutterwaveService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OnboardBankAccount
{
    protected FlutterwaveService $flwService;

    public function __construct(FlutterwaveService $flwService)
    {
        $this->flwService = $flwService;
    }

    public function execute(User $actor, array $payload): BankAccount
    {
        // 1. Resolve official banking name via Flutterwave Client
        $verifiedName = $this->flwService->resolveBankAccount(
            $payload['account_number'],
            $payload['bank_code']
        );

        return DB::transaction(function () use ($actor, $payload, $verifiedName) {

            $userId = null;
            $storeId = null;

            if (!empty($payload['store_id'])) {
                // Ensure actor actually owns or manages this store
                $storeId = (int) $payload['store_id'];
                $hasAccess = DB::table('stores')
                    ->where('id', $storeId)
                    ->where('owner_id', $actor->id) // Adjust according to your exact schema configuration
                    ->exists();

                if (!$hasAccess) {
                    throw new AccessDeniedHttpException('Unauthorized store management context.');
                }

                // Demote old primary store bank accounts if resetting
                BankAccount::where('store_id', $storeId)->update(['is_primary' => false]);
            } else {
                $userId = $actor->id;
                // Demote old primary driver bank accounts if resetting
                BankAccount::where('user_id', $userId)->update(['is_primary' => false]);
            }

            // 2. Persist verified bank details
            return BankAccount::create([
                'user_id'        => $userId,
                'store_id'       => $storeId,
                'bank_code'      => $payload['bank_code'],
                'bank_name'      => $payload['bank_name'],
                'account_number' => $payload['account_number'],
                'account_name'   => $verifiedName, // Locked down source of truth
                'is_primary'     => true,
            ]);
        });
    }
}
