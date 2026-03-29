<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('storefront.admin', [
            'customer' => $request->user(),
            'isSuperAdmin' => $request->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
