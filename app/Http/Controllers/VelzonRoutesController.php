<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class VelzonRoutesController extends Controller
{
    public function auth_signin_basic(): Response
    {
        return Inertia::render('auth-pages/signin/basic', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }
}
