<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function home(): View
    {
        $shop = Shop::first();

        if (! $shop) {
            abort(404, 'No shop has been created yet.');
        }

        return view('home', compact('shop'));
    }
}