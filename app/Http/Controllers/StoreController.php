<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        return view('store.index', [
            'products' => Product::query()->orderBy('price')->get(),
        ]);
    }
}
