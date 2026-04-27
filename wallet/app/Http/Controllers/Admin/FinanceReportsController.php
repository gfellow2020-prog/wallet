<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Models\ProductSale;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportsController extends Controller
{
    /**
     * @return array{from: Carbon, to: Carbon}
     */
    protected function dateRange(Request $request): array
    {
        $fromRaw = (string) $request->query('date_from', '');
        $toRaw = (string) $request->query('date_to', '');

        $from = $fromRaw !== '' ? Carbon::parse($fromRaw)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $toRaw !== '' ? Carbon::parse($toRaw)->endOfDay() : now()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return compact('from', 'to');
    }

    protected function applyDateRange(Builder $q, Carbon $from, Carbon $to): Builder
    {
        return $q->whereBetween('created_at', [$from, $to]);
    }

    protected function applyTransactionDateRange(Builder $q, Carbon $from, Carbon $to): Builder
    {
        // `transactions` table does not have created_at; it uses `transacted_at`.
        return $q->whereBetween('transacted_at', [$from, $to]);
    }

    public function marketplace(Request $request)
    {
        $range = $this->dateRange($request);

        $q = ProductSale::query()
            ->with(['product:id,title', 'buyer:id,name,email', 'seller:id,name,email'])
            ->when($request->query('status'), fn ($qb, $s) => $qb->where('status', $s));

        $q = $this->applyDateRange($q, $range['from'], $range['to']);

        if ($request->query('q')) {
            $s = (string) $request->query('q');
            $q->where(function ($qq) use ($s) {
                $qq->where('reference', 'like', "%{$s}%")
                    ->orWhere('checkout_reference', 'like', "%{$s}%")
                    ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$s}%"))
                    ->orWhereHas('buyer', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
                    ->orWhereHas('seller', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            });
        }

        $totalsBase = (clone $q)->getQuery();
        $totals = [
            'gross' => (float) (clone $totalsBase)->sum('gross_amount'),
            'admin_fee' => (float) (clone $totalsBase)->sum('admin_fee'),
            'cashback' => (float) (clone $totalsBase)->sum('cashback_amount'),
            'seller_net' => (float) (clone $totalsBase)->sum('seller_net'),
            'count' => (int) (clone $totalsBase)->count(),
        ];

        $sales = $q->latest()->paginate(25)->withQueryString();

        return view('admin.finance.marketplace', [
            'sales' => $sales,
            'totals' => $totals,
            'range' => $range,
            'statuses' => ['completed', 'refunded', 'pending'],
        ]);
    }

    public function settlements(Request $request)
    {
        $range = $this->dateRange($request);

        $orders = Order::query()
            ->with('merchant:id,name,code')
            ->when($request->query('status'), fn ($qb, $s) => $qb->where('status', $s));

        $orders = $this->applyDateRange($orders, $range['from'], $range['to']);

        if ($request->query('q')) {
            $s = (string) $request->query('q');
            $orders->where(function ($qq) use ($s) {
                $qq->where('order_reference', 'like', "%{$s}%")
                    ->orWhereHas('merchant', fn ($m) => $m->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
            });
        }

        $grouped = (clone $orders)
            ->select('merchant_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(gross_amount) as gross_total')
            ->selectRaw('SUM(fee_amount) as fee_total')
            ->selectRaw('SUM(gross_amount - fee_amount) as net_total')
            ->groupBy('merchant_id')
            ->orderByDesc(DB::raw('SUM(gross_amount)'))
            ->paginate(25)
            ->withQueryString();

        $merchantIds = $grouped->pluck('merchant_id')->filter()->all();
        $merchants = DB::table('merchants')->whereIn('id', $merchantIds)->get()->keyBy('id');

        $totalsBase = (clone $orders)->getQuery();
        $totals = [
            'gross' => (float) (clone $totalsBase)->sum('gross_amount'),
            'fee' => (float) (clone $totalsBase)->sum('fee_amount'),
            'net' => (float) (clone $totalsBase)->sum(DB::raw('gross_amount - fee_amount')),
            'count' => (int) (clone $totalsBase)->count(),
        ];

        return view('admin.finance.settlements', [
            'rows' => $grouped,
            'merchants' => $merchants,
            'totals' => $totals,
            'range' => $range,
        ]);
    }

    public function revenue(Request $request)
    {
        $range = $this->dateRange($request);

        $merchantOrders = $this->applyDateRange(Order::query(), $range['from'], $range['to']);
        $marketSales = $this->applyDateRange(ProductSale::query()->where('status', 'completed'), $range['from'], $range['to']);
        $cashbacks = $this->applyDateRange(CashbackTransaction::query(), $range['from'], $range['to']);

        $totals = [
            'merchant_gross' => (float) $merchantOrders->sum('gross_amount'),
            'merchant_fee' => (float) $merchantOrders->sum('fee_amount'),
            'merchant_net' => (float) $merchantOrders->sum(DB::raw('gross_amount - fee_amount')),
            'marketplace_gross' => (float) $marketSales->sum('gross_amount'),
            'marketplace_admin_fee' => (float) $marketSales->sum('admin_fee'),
            'marketplace_seller_net' => (float) $marketSales->sum('seller_net'),
            'marketplace_cashback' => (float) $marketSales->sum('cashback_amount'),
            'cashback_ledger_total' => (float) $cashbacks->sum('cashback_amount'),
        ];

        return view('admin.finance.revenue', [
            'totals' => $totals,
            'range' => $range,
        ]);
    }

    public function reconciliation(Request $request)
    {
        $range = $this->dateRange($request);
        $stuckHours = max(1, (int) $request->query('stuck_hours', 24));
        $stuckCutoff = now()->subHours($stuckHours);

        $paymentsMissingProviderRef = $this->applyDateRange(
            \App\Models\Payment::query()->whereNull('provider_reference'),
            $range['from'],
            $range['to']
        )->latest()->paginate(15, ['*'], 'pm')->withQueryString();

        $withdrawalsStuck = Withdrawal::query()
            ->whereIn('status', ['Requested', 'UnderReview', 'Processing'])
            ->where('created_at', '<=', $stuckCutoff)
            ->latest()
            ->paginate(15, ['*'], 'ws')
            ->withQueryString();

        $gatewayTxIssues = $this->applyTransactionDateRange(
            Transaction::query()->where(function ($q) {
                $q->whereNotIn('gateway_status', ['successful', 'success', 'local'])
                    ->orWhereNull('gateway_status');
            }),
            $range['from'],
            $range['to']
        )->latest('transacted_at')->paginate(15, ['*'], 'gt')->withQueryString();

        return view('admin.finance.reconciliation', [
            'range' => $range,
            'stuckHours' => $stuckHours,
            'paymentsMissingProviderRef' => $paymentsMissingProviderRef,
            'withdrawalsStuck' => $withdrawalsStuck,
            'gatewayTxIssues' => $gatewayTxIssues,
        ]);
    }

    public function exportMarketplace(Request $request): StreamedResponse
    {
        $range = $this->dateRange($request);

        $q = ProductSale::query()
            ->with(['product:id,title', 'buyer:id,name,email', 'seller:id,name,email'])
            ->when($request->query('status'), fn ($qb, $s) => $qb->where('status', $s));
        $q = $this->applyDateRange($q, $range['from'], $range['to']);

        if ($request->query('q')) {
            $s = (string) $request->query('q');
            $q->where(function ($qq) use ($s) {
                $qq->where('reference', 'like', "%{$s}%")
                    ->orWhere('checkout_reference', 'like', "%{$s}%")
                    ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$s}%"));
            });
        }

        $filename = 'marketplace-finance-'.$range['from']->format('Ymd').'-'.$range['to']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'reference', 'status', 'product', 'buyer', 'seller', 'gross', 'admin_fee', 'cashback', 'seller_net']);
            $q->orderByDesc('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $s) {
                    fputcsv($out, [
                        optional($s->created_at)->toDateTimeString(),
                        $s->reference,
                        $s->status,
                        $s->product?->title,
                        $s->buyer?->email,
                        $s->seller?->email,
                        $s->gross_amount,
                        $s->admin_fee,
                        $s->cashback_amount,
                        $s->seller_net,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportSettlements(Request $request): StreamedResponse
    {
        $range = $this->dateRange($request);

        $orders = Order::query()
            ->with('merchant:id,name,code')
            ->when($request->query('status'), fn ($qb, $s) => $qb->where('status', $s));
        $orders = $this->applyDateRange($orders, $range['from'], $range['to']);

        if ($request->query('q')) {
            $s = (string) $request->query('q');
            $orders->where(function ($qq) use ($s) {
                $qq->where('order_reference', 'like', "%{$s}%")
                    ->orWhereHas('merchant', fn ($m) => $m->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
            });
        }

        $filename = 'merchant-settlements-'.$range['from']->format('Ymd').'-'.$range['to']->format('Ymd').'.csv';

        $grouped = (clone $orders)
            ->select('merchant_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(gross_amount) as gross_total')
            ->selectRaw('SUM(fee_amount) as fee_total')
            ->selectRaw('SUM(gross_amount - fee_amount) as net_total')
            ->groupBy('merchant_id')
            ->orderByDesc(DB::raw('SUM(gross_amount)'));

        return response()->streamDownload(function () use ($grouped) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['merchant_id', 'merchant_name', 'merchant_code', 'orders_count', 'gross_total', 'fee_total', 'net_total']);
            $grouped->chunk(500, function ($chunk) use ($out) {
                $ids = $chunk->pluck('merchant_id')->filter()->all();
                $merchants = DB::table('merchants')->whereIn('id', $ids)->get()->keyBy('id');
                foreach ($chunk as $r) {
                    $m = $merchants[$r->merchant_id] ?? null;
                    fputcsv($out, [
                        $r->merchant_id,
                        $m?->name,
                        $m?->code,
                        $r->orders_count,
                        $r->gross_total,
                        $r->fee_total,
                        $r->net_total,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportRevenue(Request $request): StreamedResponse
    {
        $range = $this->dateRange($request);

        $merchantOrders = $this->applyDateRange(Order::query(), $range['from'], $range['to']);
        $marketSales = $this->applyDateRange(ProductSale::query()->where('status', 'completed'), $range['from'], $range['to']);
        $cashbacks = $this->applyDateRange(CashbackTransaction::query(), $range['from'], $range['to']);

        $totals = [
            'merchant_gross' => (float) $merchantOrders->sum('gross_amount'),
            'merchant_fee' => (float) $merchantOrders->sum('fee_amount'),
            'merchant_net' => (float) $merchantOrders->sum(DB::raw('gross_amount - fee_amount')),
            'marketplace_gross' => (float) $marketSales->sum('gross_amount'),
            'marketplace_admin_fee' => (float) $marketSales->sum('admin_fee'),
            'marketplace_seller_net' => (float) $marketSales->sum('seller_net'),
            'marketplace_cashback' => (float) $marketSales->sum('cashback_amount'),
            'cashback_ledger_total' => (float) $cashbacks->sum('amount'),
        ];

        $filename = 'fees-revenue-'.$range['from']->format('Ymd').'-'.$range['to']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($totals) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['metric', 'value']);
            foreach ($totals as $k => $v) {
                fputcsv($out, [$k, $v]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportReconciliation(Request $request): StreamedResponse
    {
        $range = $this->dateRange($request);
        $stuckHours = max(1, (int) $request->query('stuck_hours', 24));
        $stuckCutoff = now()->subHours($stuckHours);

        $paymentsMissingProviderRef = $this->applyDateRange(
            \App\Models\Payment::query()->whereNull('provider_reference'),
            $range['from'],
            $range['to']
        );

        $withdrawalsStuck = Withdrawal::query()
            ->whereIn('status', ['Requested', 'UnderReview', 'Processing'])
            ->where('created_at', '<=', $stuckCutoff);

        $gatewayTxIssues = $this->applyTransactionDateRange(
            Transaction::query()->where(function ($q) {
                $q->whereNotIn('gateway_status', ['successful', 'success', 'local'])
                    ->orWhereNull('gateway_status');
            }),
            $range['from'],
            $range['to']
        );

        $filename = 'reconciliation-'.$range['from']->format('Ymd').'-'.$range['to']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($paymentsMissingProviderRef, $withdrawalsStuck, $gatewayTxIssues) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['section', 'id', 'ref', 'status', 'amount', 'created_at', 'extra']);

            $paymentsMissingProviderRef->orderByDesc('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, ['payments_missing_provider_reference', $p->id, $p->payment_reference, $p->status?->value ?? $p->status, $p->amount, optional($p->created_at)->toDateTimeString(), '']);
                }
            });

            $withdrawalsStuck->orderByDesc('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $w) {
                    fputcsv($out, ['withdrawals_stuck', $w->id, $w->reference ?? '', $w->status?->value ?? $w->status, $w->amount, optional($w->created_at)->toDateTimeString(), '']);
                }
            });

            $gatewayTxIssues->orderByDesc('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $t) {
                    fputcsv($out, ['gateway_transactions_issues', $t->id, $t->gateway_reference, $t->gateway_status, $t->amount, optional($t->transacted_at)->toDateTimeString(), $t->phone_number ?? '']);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

