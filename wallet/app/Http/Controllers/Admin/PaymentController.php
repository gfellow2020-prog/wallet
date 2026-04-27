<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['user', 'merchant', 'cashback'])
            ->when(request('q'), function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('payment_reference', 'like', "%{$s}%")
                        ->orWhere('provider_reference', 'like', "%{$s}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")
                            ->orWhere('email', 'like', "%{$s}%"))
                        ->orWhereHas('order.merchant', fn ($m) => $m->where('name', 'like', "%{$s}%")
                            ->orWhere('code', 'like', "%{$s}%"));
                });
            })
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return view('admin.payments.index', compact('payments'));
    }
}
