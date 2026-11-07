<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Buyer;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Seller;
use Illuminate\Support\Facades\Route;

// Pembeli: melihat PO tidak perlu login.
Route::get('/', Buyer\HomeController::class);
Route::get('/toko', [Buyer\ShopController::class, 'index']);

// Login
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::get('/auth/google/redirect', [LoginController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback']);
Route::post('/dev/login/{user}', [LoginController::class, 'devLogin']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');

// Callback payment gateway (tanpa CSRF, diverifikasi tanda tangan)
Route::post('/webhooks/payment', PaymentWebhookController::class)->middleware('throttle:120,1');

Route::middleware('auth')->group(function () {
    Route::post('/checkout', [Buyer\CheckoutController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/bayar/{order}', [Buyer\PaymentController::class, 'show']);
    Route::post('/bayar/{order}/batal', [Buyer\PaymentController::class, 'cancel']);
    Route::post('/bayar/{order}/simulasi', [Buyer\PaymentController::class, 'simulate']);
    Route::get('/api/orders/{order}/status', [Buyer\PaymentController::class, 'status']);
    Route::get('/pesanan', [Buyer\OrderController::class, 'index']);
    Route::get('/pesanan/{order}', [Buyer\OrderController::class, 'show']);
    Route::get('/pesanan/{order}/komplain', [Buyer\ComplaintController::class, 'create']);
    Route::post('/pesanan/{order}/komplain', [Buyer\ComplaintController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/akun', [Buyer\AccountController::class, 'show']);
    Route::put('/akun', [Buyer\AccountController::class, 'update']);

    // Penjual
    Route::get('/seller/daftar', [Seller\RegisterController::class, 'create']);
    Route::post('/seller/daftar', [Seller\RegisterController::class, 'store']);

    Route::prefix('seller')->middleware('shop-member')->group(function () {
        Route::get('/', [Seller\DashboardController::class, 'index']);
        Route::post('/toko', [Seller\DashboardController::class, 'switchShop']);
        Route::get('/scan', [Seller\ScanController::class, 'index']);
        Route::post('/scan', [Seller\ScanController::class, 'store'])->middleware('throttle:120,1');
        Route::get('/saldo', [Seller\BalanceController::class, 'index']);
        Route::post('/saldo/pencairan', [Seller\BalanceController::class, 'store'])->middleware('shop-admin');
        Route::get('/po', [Seller\PreorderController::class, 'index']);
        Route::get('/po/baru', [Seller\PreorderController::class, 'create']);
        Route::post('/po', [Seller\PreorderController::class, 'store']);
        Route::get('/po/{preorder}/ubah', [Seller\PreorderController::class, 'edit'])->whereNumber('preorder');
        Route::put('/po/{preorder}', [Seller\PreorderController::class, 'update'])->whereNumber('preorder');
        Route::post('/po/{preorder}/tutup', [Seller\PreorderController::class, 'close'])->whereNumber('preorder');
        Route::delete('/po/{preorder}', [Seller\PreorderController::class, 'destroy'])->whereNumber('preorder');
        Route::middleware('shop-admin')->group(function () {
            Route::get('/pengaturan', [Seller\SettingsController::class, 'show']);
            Route::post('/pengaturan', [Seller\SettingsController::class, 'update']);
            Route::post('/pengaturan/rekening', [Seller\SettingsController::class, 'requestAccount']);
            Route::delete('/pengaturan/rekening', [Seller\SettingsController::class, 'cancelAccount']);
        });
        Route::get('/komplain', [Seller\ComplaintController::class, 'index']);
        Route::post('/komplain/{complaint}/setujui', [Seller\ComplaintController::class, 'approve'])->whereNumber('complaint');
        Route::post('/komplain/{complaint}/tolak', [Seller\ComplaintController::class, 'reject'])->whereNumber('complaint');
    });

    // Super admin
    Route::prefix('admin')->middleware('super-admin')->group(function () {
        Route::get('/', Admin\OverviewController::class);
        Route::post('/pencairan/{payout}/transfer', [Admin\PayoutController::class, 'transfer']);
        Route::post('/pencairan/{payout}/tolak', [Admin\PayoutController::class, 'reject']);
        Route::post('/toko/{shop:id}/setujui', [Admin\ShopController::class, 'approve']);
        Route::post('/toko/{shop:id}/tolak', [Admin\ShopController::class, 'reject']);
        Route::post('/pesanan/{order}/cek-status', [Admin\PaymentController::class, 'checkStatus']);
        Route::post('/pesanan/{order}/lunasi', [Admin\PaymentController::class, 'markPaid']);
        Route::post('/pesanan/{order}/refund', [Admin\PaymentController::class, 'refund']);
        Route::post('/rekening/{account}/setujui', [Admin\BankAccountController::class, 'approve']);
        Route::post('/rekening/{account}/tolak', [Admin\BankAccountController::class, 'reject']);
        Route::get('/komplain', [Admin\ComplaintController::class, 'index']);
        Route::post('/komplain/{complaint}/putuskan', [Admin\ComplaintController::class, 'decide']);
        Route::post('/refund/{refund}/selesai', [Admin\ComplaintController::class, 'transferred']);
    });
});

// Halaman toko dan PO ditaruh paling akhir karena alamatnya bebas (jajapo.app/<toko>/<po>).
$reserved = implode('|', config('jajapo.reserved_slugs'));
Route::get('/{shop}', [Buyer\ShopController::class, 'show'])->where('shop', "(?!(?:{$reserved})(?:/|$))[a-z0-9-]+");
Route::get('/{shop}/{preorder}', [Buyer\PreorderController::class, 'show'])
    ->where('shop', "(?!(?:{$reserved})(?:/|$))[a-z0-9-]+")
    ->where('preorder', '[a-z0-9-]+');
