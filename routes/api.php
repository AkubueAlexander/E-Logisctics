<?php

use App\Http\Controllers\Api\Admin\AdminGlobalCategoryController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\CheckoutController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Driver\ArrivalController;
use App\Http\Controllers\Api\Driver\DeliveryController;
use App\Http\Controllers\Api\Driver\DriverController;
use App\Http\Controllers\Api\Driver\DriverOrderTransitController;
use App\Http\Controllers\Api\Driver\LocationController;
use App\Http\Controllers\Api\Driver\OrderAcceptanceController;
use App\Http\Controllers\Api\Driver\OrderExceptionController;
use App\Http\Controllers\Api\Driver\OrderRejectionController;
use App\Http\Controllers\Api\Driver\PickupController;
use App\Http\Controllers\Api\Driver\TelemetryController;
use App\Http\Controllers\Api\Driver\WalletController;
use App\Http\Controllers\Api\Financial\BankOnboardingController;
use App\Http\Controllers\Api\Financial\FlutterwaveTransferWebhookController;
use App\Http\Controllers\Api\Financial\WalletSummaryController;
use App\Http\Controllers\Api\Financial\WithdrawalController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\Order\MerchantOrderController;
use App\Http\Controllers\Api\Order\OrderDisputeController;
use App\Http\Controllers\Api\Order\OrderReviewController;
use App\Http\Controllers\Api\Payment\FlutterwaveVerifyPaymentController;
use App\Http\Controllers\Api\Payment\FlutterwaveWebhookController;
use App\Http\Controllers\Api\Payment\FlutterwaveWebhookRouterController;
use App\Http\Controllers\Api\Payment\InitializePaymentController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Product\ProductModifierSyncController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\ResendOtpController;
use App\Http\Controllers\Api\Store\MenuCategoryController;
use App\Http\Controllers\Api\Store\StoreController;
use App\Http\Controllers\Api\Store\StoreFinanceController;
use App\Http\Controllers\Api\Store\StoreStaffController;
use App\Http\Controllers\Api\Store\StoreStatusController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\VerifyOtpController;
use App\Http\Controllers\Api\VerifyTwoFactorLoginController;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    // 1. Browser Redirect Verification Target (Handles Customer UX)
    Route::get('/payments/verify-payment', FlutterwaveVerifyPaymentController::class)
        ->name('payment.verify');

// 2. Universal Webhook Entrypoint (Paste this single URL into Flutterwave Dashboard)
    Route::post('/payments/flutterwave/webhook', FlutterwaveWebhookRouterController::class)
        ->name('webhooks.flutterwave');

    Route::prefix('auth')->group(function () {
        // Registration & Verification
        Route::post('/register', RegisterController::class);
        Route::post('/verify-otp', VerifyOtpController::class);
        Route::post('/resend-otp', ResendOtpController::class);

        // Authentication & 2FA Challenge
        Route::post('/login', LoginController::class);
        Route::post('/recover-two-factor', [TwoFactorController::class, 'recoverTwoFactor']);
        Route::post('/verify-2fa', VerifyTwoFactorLoginController::class);

        // Password Recovery via OTPs
        Route::post('/reset-otp', [ForgotPasswordController::class, 'sendResetOtp']);
        Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

        // Protected Auth Routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/2fa/enable', [TwoFactorController::class, 'enableTwoFactor']);
            Route::post('/2fa/confirm', [TwoFactorController::class, 'confirmTwoFactor']);
            Route::post('/2fa/disable', [TwoFactorController::class, 'disableTwoFactor']);
        });

        Route::get('financial/wallet/summary', WalletSummaryController::class);
    });

    Route::middleware('auth:sanctum')->group(function () {

        // --- Universal Profile ---
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::post('financial/banks/onboard', BankOnboardingController::class);
        Route::post('financial/wallet/withdraw', WithdrawalController::class);


        // --- Driver Domain ---
        Route::middleware('role:driver')->prefix('driver')->group(function () {
            Route::patch('/profile', [DriverController::class, 'updateProfile']);
            Route::patch('/status', [DriverController::class, 'toggleAvailability']);

            Route::post('location', [LocationController::class, 'update']);

            Route::post('sub-orders/{subOrder}/arrive', ArrivalController::class);
            Route::post('sub-orders/{subOrder}/pickup', PickupController::class);
            Route::post('sub-orders/{subOrder}/deliver', DeliveryController::class);

            Route::post('telemetry/location', TelemetryController::class);
            Route::get('wallet/summary', [WalletController::class, 'index']);

            Route::get('wallet', [WalletController::class, 'index']);
            Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);

            Route::post('orders/{order}/no-show', [OrderExceptionController::class, 'triggerNoShow']);

            Route::post('/pings/{ping}/accept', OrderAcceptanceController::class);
            Route::post('/pings/{ping}/reject', OrderRejectionController::class);

            Route::post('orders/{order}/arrive', [DriverOrderTransitController::class, 'arriveAtMerchant']);
            Route::post('orders/{order}/collect', [DriverOrderTransitController::class, 'collectOrder']);
            Route::post('orders/{order}/complete', [DriverOrderTransitController::class, 'completeDelivery']);


        });

        // --- Store Manager Domain (Grouped) ---
        Route::middleware('role:store_manager')->group(function () {
            Route::prefix('store')->group(function () {

                // URL: /v1/stores
                Route::post('/', [StoreController::class, 'store']);

                // URL: /v1/stores/{store}
                Route::put('/{store}', [StoreController::class, 'update']);

                // URL: /v1/store/categories
                Route::post('/categories/{store}', [MenuCategoryController::class, 'store']);


                // Nested staff route (Maintains original URL: /v1/store/staff/invite)
                Route::prefix('staff')->group(function () {
                    Route::post('/invite', [StoreStaffController::class, 'invite']);
                });
            });

            Route::prefix('product')->group(function () {


                Route::post('/{store}/product', [ProductController::class, 'store']);

                Route::put('{store}/product/{product}', [ProductController::class, 'update']);

                Route::post('{product}/modifiers/sync', [ProductModifierSyncController::class, 'store']);
            });

            Route::prefix('store/{store}')->group(function () {

                // Merchant Order Handling Pipeline
                Route::get('orders', [MerchantOrderController::class, 'index']);
                Route::patch('orders/{subOrder}/status', [MerchantOrderController::class, 'update']);

                Route::patch('toggle-status', StoreStatusController::class);

                Route::get('finance/summary', StoreFinanceController::class);
            });
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {

            // Taxonomy and Global Super-category generation
            Route::post('/global-categories', [AdminGlobalCategoryController::class, 'store']);

        });

        Route::middleware('role:customer')->prefix('customer')->group(function () {

            // Spatial search and discovery
            Route::get('/stores/nearby', [App\Http\Controllers\Api\Customer\StoreDiscoveryController::class, 'index']);

            // Cached single store storefront menu profile
            Route::get('/stores/{store}/menu', [App\Http\Controllers\Api\Customer\StoreDiscoveryController::class, 'show']);

            Route::post('orders/{order_id}/pay', [InitializePaymentController::class, 'initialize']);

            Route::get('cart', [CartController::class, 'index']);
            Route::post('cart', [CartController::class, 'store']);
            Route::post('cart/sync', [CartController::class, 'sync']);
            Route::delete('cart/{itemKey}', [CartController::class, 'destroy']);

            Route::get('orders', [CustomerOrderController::class, 'index']);
            Route::get('orders/{order}', [CustomerOrderController::class, 'show']);


            Route::post('checkout', CheckoutController::class);

            Route::post('orders/{order}/rate', OrderReviewController::class);

            Route::post('orders/dispute', OrderDisputeController::class);
        });



    });
});
