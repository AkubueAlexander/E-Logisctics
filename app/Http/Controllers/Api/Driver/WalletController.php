<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Services\DriverWalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    protected DriverWalletService $walletService;

    public function __construct(DriverWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Fetch the driver's real-time statement layout.
     */
    public function index(Request $request): JsonResponse
    {
        $metrics = $this->walletService->getMetrics($request->user());

        return response()->json([
            'success' => true,
            'data'    => $metrics
        ], 200);
    }
}
