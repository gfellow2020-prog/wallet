<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use App\Models\KycRecord;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\ProductSale;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $completedSales = ProductSale::query()->where('status', 'completed');
        $completedSalesToday = (clone $completedSales)->whereDate('created_at', today());

        $stats = [
            'users' => User::count(),
            'payments_today' => Payment::whereDate('created_at', today())->sum('amount'),
            'payments_today_count' => Payment::whereDate('created_at', today())->count(),
            'withdrawals_pending' => Withdrawal::whereIn('status', ['Requested', 'UnderReview'])->count(),
            'kyc_pending' => KycRecord::where('status', 'Pending')->count(),
            'merchants_active' => Merchant::where('is_active', true)->count(),
            'fraud_open' => FraudFlag::whereNull('resolved_at')->count(),
            'marketplace_gross' => (clone $completedSales)->sum('gross_amount'),
            'marketplace_gross_today' => (clone $completedSalesToday)->sum('gross_amount'),
            'marketplace_admin_fee' => (clone $completedSales)->sum('admin_fee'),
            'marketplace_admin_fee_today' => (clone $completedSalesToday)->sum('admin_fee'),
            'marketplace_cashback' => (clone $completedSales)->sum('cashback_amount'),
            'marketplace_cashback_today' => (clone $completedSalesToday)->sum('cashback_amount'),
            'marketplace_seller_net' => (clone $completedSales)->sum('seller_net'),
            'marketplace_sales_count' => (clone $completedSales)->count(),
            'marketplace_sales_today_count' => (clone $completedSalesToday)->count(),
            'marketplace_average_order' => round((float) ((clone $completedSales)->avg('gross_amount') ?? 0), 2),
            // Platform-wide: all users / all sellers (query builder, not tied to the logged-in admin)
            'items_sold_total' => (int) DB::table('product_sales')
                ->where('status', 'completed')
                ->sum('quantity'),
            'products_total' => (int) DB::table('products')->count(),
            'stock_units_total' => (int) DB::table('products')->sum('stock'),
        ];

        $recentPayments = Payment::with(['user', 'merchant'])
            ->latest()
            ->limit(10)
            ->get();

        $recentProductSales = ProductSale::with(['product', 'buyer', 'seller'])
            ->latest()
            ->limit(10)
            ->get();

        $topSellers = ProductSale::query()
            ->select('seller_id')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(gross_amount) as gross_total')
            ->selectRaw('SUM(seller_net) as seller_net_total')
            ->where('status', 'completed')
            ->with('seller:id,name')
            ->groupBy('seller_id')
            ->orderByDesc(DB::raw('SUM(gross_amount)'))
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentPayments', 'recentProductSales', 'topSellers'));
    }
}
