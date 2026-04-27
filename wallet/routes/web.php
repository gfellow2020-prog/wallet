<?php

use App\Http\Controllers\Admin\AdjustmentController as AdminAdjustment;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLog;
use App\Http\Controllers\Admin\CashbackController as AdminCashback;
use App\Http\Controllers\Admin\CategoryController as AdminCategory;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\FinanceReportsController as AdminFinanceReports;
use App\Http\Controllers\Admin\FraudController as AdminFraud;
use App\Http\Controllers\Admin\KycController as AdminKyc;
use App\Http\Controllers\Admin\MerchantController as AdminMerchant;
use App\Http\Controllers\Admin\OrderController as AdminOrder;
use App\Http\Controllers\Admin\PaymentController as AdminPayment;
use App\Http\Controllers\Admin\PermissionController as AdminPermission;
use App\Http\Controllers\Admin\ProfileController as AdminProfile;
use App\Http\Controllers\Admin\RoleController as AdminRole;
use App\Http\Controllers\Admin\SettingsController as AdminSettings;
use App\Http\Controllers\Admin\UserController as AdminUser;
use App\Http\Controllers\Admin\WalletListController as AdminWalletList;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawal;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\LencoWebhookController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletMessagesController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/lenco/{action}', [LencoWebhookController::class, 'handle'])
    ->where('action', '[a-z]+');

Route::middleware(['auth', 'not_admin'])->group(function () {
    Route::get('/', [WalletController::class, 'index'])->name('wallet.home');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');

    Route::get('/wallet/fund', [WalletController::class, 'fundForm'])->name('wallet.fund');
    Route::post('/wallet/fund', [WalletController::class, 'fund'])->name('wallet.fund.store');

    Route::get('/wallet/send', [WalletController::class, 'sendForm'])->name('wallet.send');
    Route::post('/wallet/send', [WalletController::class, 'send'])->name('wallet.send.store');

    Route::get('/wallet/pay', [WalletController::class, 'payForm'])->name('wallet.pay');
    Route::post('/wallet/pay', [WalletController::class, 'pay'])->name('wallet.pay.store');

    Route::get('/wallet/history', [WalletController::class, 'history'])->name('wallet.history');

    Route::get('/wallet/transaction/{transaction}/status', [WalletController::class, 'checkStatus'])->name('wallet.transaction.status');
    Route::get('/wallet/gateway-balance', [WalletController::class, 'gatewayBalance'])->name('wallet.gateway.balance');
    Route::post('/wallet/name-lookup', [WalletController::class, 'nameLookup'])->name('wallet.name.lookup');

    Route::get('/profile', [WalletController::class, 'profile'])->name('profile');

    // Messages (user-to-user)
    Route::get('/messages', [WalletMessagesController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [WalletMessagesController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [WalletMessagesController::class, 'send'])->middleware('throttle:30,1')->name('messages.send');
    Route::get('/message-attachments/{attachment}', [WalletMessagesController::class, 'downloadAttachment'])->middleware('throttle:120,1')->name('messages.attachments.download');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

/* ── Admin Portal ────────────────────────────────── */
Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class,  'index'])->name('dashboard');

    // My profile
    Route::get('/profile', [AdminProfile::class, 'show'])->name('profile.show');
    Route::post('/profile', [AdminProfile::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [AdminProfile::class, 'updatePassword'])->name('profile.password');

    // Users
    Route::get('/users', [AdminUser::class,       'index'])->name('users');
    Route::get('/users/{user}', [AdminUser::class,       'show'])->name('users.show');
    Route::post('/users/{user}/fund-wallet', [AdminUser::class,       'fundWallet'])->name('users.fund-wallet');
    Route::post('/users/{user}/roles', [AdminUser::class,       'syncRoles'])->middleware('can:users.assign_roles')->name('users.roles.sync');
    Route::post('/users/{user}/suspend', [AdminUser::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [AdminUser::class, 'unsuspend'])->name('users.unsuspend');
    Route::post('/users/{user}/update', [AdminUser::class, 'updateProfile'])->name('users.update');
    Route::post('/users/{user}/password', [AdminUser::class, 'resetPassword'])->name('users.password.reset');
    Route::post('/users/{user}/force-logout', [AdminUser::class, 'forceLogout'])->name('users.force-logout');
    Route::post('/users/{user}/otp-reset', [AdminUser::class, 'resetOtpLockouts'])->name('users.otp-reset');
    Route::post('/users/{user}/notes', [AdminUser::class, 'addNote'])->name('users.notes');

    // Roles & permissions
    Route::get('/roles', [AdminRole::class, 'index'])->middleware('can:roles.view')->name('roles.index');
    Route::get('/roles/create', [AdminRole::class, 'create'])->middleware('can:roles.manage')->name('roles.create');
    Route::post('/roles', [AdminRole::class, 'store'])->middleware('can:roles.manage')->name('roles.store');
    Route::get('/roles/{role}', [AdminRole::class, 'show'])->middleware('can:roles.view')->name('roles.show');
    Route::put('/roles/{role}', [AdminRole::class, 'update'])->middleware('can:roles.manage')->name('roles.update');
    Route::delete('/roles/{role}', [AdminRole::class, 'destroy'])->middleware('can:roles.manage')->name('roles.destroy');
    Route::post('/roles/{role}/permissions', [AdminRole::class, 'syncPermissions'])->middleware('can:roles.manage')->name('roles.permissions.sync');

    Route::get('/permissions', [AdminPermission::class, 'index'])->middleware('can:permissions.view')->name('permissions.index');
    Route::get('/permissions/create', [AdminPermission::class, 'create'])->middleware('can:permissions.manage')->name('permissions.create');
    Route::post('/permissions', [AdminPermission::class, 'store'])->middleware('can:permissions.manage')->name('permissions.store');
    Route::put('/permissions/{permission}', [AdminPermission::class, 'update'])->middleware('can:permissions.manage')->name('permissions.update');
    Route::delete('/permissions/{permission}', [AdminPermission::class, 'destroy'])->middleware('can:permissions.manage')->name('permissions.destroy');

    // KYC
    Route::get('/kyc', [AdminKyc::class,        'index'])->middleware('can:kyc.view')->name('kyc');
    Route::get('/kyc/{kyc}', [AdminKyc::class,        'show'])->middleware('can:kyc.view')->name('kyc.show');
    Route::post('/kyc/{kyc}/review', [AdminKyc::class,        'review'])->middleware('can:kyc.review')->name('kyc.review');

    // Merchants
    Route::get('/merchants', [AdminMerchant::class,   'index'])->middleware('can:merchants.update')->name('merchants');
    Route::post('/merchants', [AdminMerchant::class,   'store'])->middleware('can:merchants.create')->name('merchants.store');
    Route::post('/merchants/{merchant}/toggle', [AdminMerchant::class,   'toggle'])->middleware('can:merchants.update')->name('merchants.toggle');

    // Categories (marketplace)
    Route::get('/categories', [AdminCategory::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [AdminCategory::class, 'create'])->name('categories.create');
    Route::post('/categories', [AdminCategory::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategory::class, 'edit'])->name('categories.edit');
    Route::post('/categories/{category}', [AdminCategory::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/toggle', [AdminCategory::class, 'toggle'])->name('categories.toggle');

    // Marketplace orders (product sales)
    Route::get('/orders', [AdminOrder::class,      'index'])->middleware('can:orders.view')->name('orders');

    // Payments
    Route::get('/payments', [AdminPayment::class,    'index'])->middleware('can:payments.view')->name('payments');

    // Cashback
    Route::get('/cashbacks', [AdminCashback::class,   'index'])->name('cashbacks');

    // Withdrawals
    Route::get('/withdrawals', [AdminWithdrawal::class, 'index'])->middleware('can:withdrawals.view')->name('withdrawals');
    Route::post('/withdrawals/{withdrawal}/action', [AdminWithdrawal::class, 'action'])->middleware('can:withdrawals.action')->name('withdrawals.action');

    // Fraud
    Route::get('/fraud', [AdminFraud::class,      'index'])->middleware('can:fraud.view')->name('fraud');
    Route::post('/fraud/{flag}/resolve', [AdminFraud::class,      'resolve'])->middleware('can:fraud.resolve')->name('fraud.resolve');

    // Audit / Adjustments
    Route::get('/audit', [AdminAuditLog::class,   'index'])->name('audit');
    Route::get('/adjustments', [AdminAdjustment::class, 'index'])->middleware('can:adjustments.create')->name('adjustments');

    // Wallets
    Route::get('/wallets', [AdminWalletList::class, 'index'])->name('wallets');

    // Settings
    Route::get('/settings', [AdminSettings::class,   'index'])->middleware('can:settings.view')->name('settings');
    Route::post('/settings', [AdminSettings::class,   'update'])->middleware('can:settings.update')->name('settings.update');
    Route::post('/settings/lenco/test', [AdminSettings::class,   'testLenco'])->middleware('can:settings.update')->name('settings.lenco.test');
    Route::get('/settings/lenco/test', function () {
        return redirect()->route('admin.settings');
    });

    // Finance department
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/marketplace', [AdminFinanceReports::class, 'marketplace'])->name('marketplace');
        Route::get('/settlements', [AdminFinanceReports::class, 'settlements'])->name('settlements');
        Route::get('/revenue', [AdminFinanceReports::class, 'revenue'])->name('revenue');
        Route::get('/reconciliation', [AdminFinanceReports::class, 'reconciliation'])->name('reconciliation');

        // CSV exports
        Route::get('/marketplace.csv', [AdminFinanceReports::class, 'exportMarketplace'])->name('marketplace.export');
        Route::get('/settlements.csv', [AdminFinanceReports::class, 'exportSettlements'])->name('settlements.export');
        Route::get('/revenue.csv', [AdminFinanceReports::class, 'exportRevenue'])->name('revenue.export');
        Route::get('/reconciliation.csv', [AdminFinanceReports::class, 'exportReconciliation'])->name('reconciliation.export');
    });
});

/* ── ExtraCash (GeePay) gateway webhooks ─────────── */
Route::post('/webhook/extracash', [WalletController::class, 'extracashGatewayCallback'])->name('extracash.gateway.callback');
Route::post('/webhook/geepay', [WalletController::class, 'extracashGatewayCallback'])->name('geepay.callback');

// --- Authentication routes (register / login / password reset) ---
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register.show');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.store');
    Route::get('/verify-phone', [PhoneVerificationController::class, 'show'])->name('phone.verify.show');
    Route::post('/verify-phone', [PhoneVerificationController::class, 'verify'])->name('phone.verify.perform');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.perform');

    Route::get('/password/reset', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');

    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');
});
