<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Buyer;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
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
    Route::get('/masukan', [FeedbackController::class, 'create']);
    Route::post('/masukan', [FeedbackController::class, 'store'])->middleware('throttle:5,1');

    // Penjual
    Route::get('/notifikasi', [NotificationController::class, 'index']);
    Route::get('/notifikasi/{id}', [NotificationController::class, 'open'])->whereUuid('id');
    Route::post('/notifikasi/dibaca', [NotificationController::class, 'readAll']);
    Route::post('/notifikasi/push', [NotificationController::class, 'subscribe'])->middleware('throttle:20,1');
    Route::delete('/notifikasi/push', [NotificationController::class, 'unsubscribe']);
    Route::get('/undangan/{token}', [InvitationController::class, 'show']);
    Route::post('/undangan/{token}', [InvitationController::class, 'accept']);
    Route::get('/seller/daftar', [Seller\RegisterController::class, 'create']);
    Route::post('/seller/daftar', [Seller\RegisterController::class, 'store']);

    Route::prefix('seller')->middleware('shop-member')->group(function () {
        Route::get('/', [Seller\DashboardController::class, 'index']);
        Route::post('/toko', [Seller\DashboardController::class, 'switchShop']);
        Route::get('/toko-baru', [Seller\RegisterController::class, 'createAnother']);
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
            Route::get('/anggota', [Seller\MemberController::class, 'index']);
            Route::post('/anggota/undang', [Seller\MemberController::class, 'invite'])->middleware('throttle:30,1');
            Route::delete('/anggota/undangan/{invitation}', [Seller\MemberController::class, 'cancelInvitation'])->whereNumber('invitation');
            Route::post('/anggota/{member}/peran', [Seller\MemberController::class, 'updateRole'])->whereNumber('member');
            Route::delete('/anggota/{member}', [Seller\MemberController::class, 'remove'])->whereNumber('member');
        });
        Route::get('/po/{preorder}/lpj', [Seller\ReportController::class, 'lpj'])->whereNumber('preorder');
        Route::get('/po/{preorder}/ekspor', [Seller\ReportController::class, 'export'])->whereNumber('preorder');
        Route::post('/pesanan/siap', [Seller\PickupController::class, 'ready']);
        Route::get('/pesanan', [Seller\OrderController::class, 'index']);
        Route::get('/rekap', [Seller\ProductionController::class, 'index']);
        Route::get('/pesanan/{code}', [Seller\OrderController::class, 'show']);
        Route::post('/pesanan/{code}/diserahkan', [Seller\OrderController::class, 'handOver']);
        Route::get('/antar', [Seller\DeliveryController::class, 'index']);
        Route::post('/antar/mulai', [Seller\DeliveryController::class, 'start']);
        Route::post('/antar/{code}/terkirim', [Seller\DeliveryController::class, 'delivered']);
        Route::get('/komplain', [Seller\ComplaintController::class, 'index']);
        Route::post('/komplain/{complaint}/setujui', [Seller\ComplaintController::class, 'approve'])->whereNumber('complaint');
        Route::post('/komplain/{complaint}/tolak', [Seller\ComplaintController::class, 'reject'])->whereNumber('complaint');
    });

    // Super admin
    Route::prefix('admin')->middleware('super-admin')->group(function () {
        Route::get('/', Admin\OverviewController::class);
        Route::get('/cari', Admin\SearchController::class);
        Route::get('/pencairan', [Admin\PayoutController::class, 'index']);
        Route::post('/pencairan/{payout}/transfer', [Admin\PayoutController::class, 'transfer']);
        Route::post('/pencairan/{payout}/tolak', [Admin\PayoutController::class, 'reject']);
        Route::get('/pembayaran', [Admin\PaymentController::class, 'index']);
        Route::get('/toko', [Admin\ShopController::class, 'index']);
        Route::get('/toko/{shop}', Admin\ShopDetailController::class);
        Route::post('/toko/{shop:id}/setujui', [Admin\ShopController::class, 'approve']);
        Route::post('/toko/{shop:id}/tolak', [Admin\ShopController::class, 'reject']);
        Route::post('/toko/{shop:id}/bekukan', [Admin\ShopController::class, 'suspend']);
        Route::post('/toko/{shop:id}/aktifkan', [Admin\ShopController::class, 'reactivate']);
        Route::post('/pesanan/{order}/cek-status', [Admin\PaymentController::class, 'checkStatus']);
        Route::post('/pesanan/{order}/lunasi', [Admin\PaymentController::class, 'markPaid']);
        Route::post('/pesanan/{order}/refund', [Admin\PaymentController::class, 'refund']);
        Route::post('/rekening/{account}/setujui', [Admin\BankAccountController::class, 'approve']);
        Route::post('/rekening/{account}/tolak', [Admin\BankAccountController::class, 'reject']);
        Route::get('/rekonsiliasi', [Admin\ReconciliationController::class, 'index']);
        Route::get('/rekonsiliasi/ekspor', [Admin\ReconciliationController::class, 'export']);
        Route::get('/log', Admin\AuditLogController::class);
        Route::get('/pengaturan', [Admin\SettingsController::class, 'show']);
        Route::post('/pengaturan', [Admin\SettingsController::class, 'update']);
        Route::get('/komplain', [Admin\ComplaintController::class, 'index']);
        Route::post('/komplain/{complaint}/putuskan', [Admin\ComplaintController::class, 'decide']);
        Route::post('/refund/{refund}/selesai', [Admin\ComplaintController::class, 'transferred']);
        Route::get('/masukan', [Admin\FeedbackController::class, 'index']);
        Route::get('/bank', [Admin\BankController::class, 'index']);
        Route::post('/bank', [Admin\BankController::class, 'store']);
        Route::put('/bank/{bank}', [Admin\BankController::class, 'update'])->whereNumber('bank');
        Route::delete('/bank/{bank}', [Admin\BankController::class, 'destroy'])->whereNumber('bank');
        Route::post('/masukan/{feedback}/status', [Admin\FeedbackController::class, 'update'])->whereNumber('feedback');
    });
});

// Halaman toko dan PO ditaruh paling akhir karena alamatnya bebas (jajapo.app/<toko>/<po>).
$reserved = implode('|', config('jajapo.reserved_slugs'));
Route::get('/{shop}', [Buyer\ShopController::class, 'show'])->where('shop', "(?!(?:{$reserved})(?:/|$))[a-z0-9-]+");
Route::get('/{shop}/{preorder}', [Buyer\PreorderController::class, 'show'])
    ->where('shop', "(?!(?:{$reserved})(?:/|$))[a-z0-9-]+")
    ->where('preorder', '[a-z0-9-]+');
