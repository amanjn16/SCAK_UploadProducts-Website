<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AuthController extends Controller
{
    public function show(): RedirectResponse
    {
        return redirect()->route('catalog');
    }
}
