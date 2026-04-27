<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSale;

class OrderController extends Controller
{
    public function index()
    {
        $query = ProductSale::query()
            ->with(['product', 'buyer', 'seller']);

        if (request('status') && in_array(request('status'), ['completed', 'refunded'], true)) {
            $query->where('status', request('status'));
        }

        $orders = $query
            ->when(request('q'), function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->whereHas('product', function ($p) use ($search) {
                        $p->where('title', 'like', "%{$search}%");
                    })->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }
}
