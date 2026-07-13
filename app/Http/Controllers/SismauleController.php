<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Comuna;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class SismauleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user()->load('establecimiento.comuna');
        $establecimiento = $user->establecimiento;
        $comunas = Comuna::all();
        $servers = config('app.servers');

        return Inertia::render('Sismaule/Index', [
            'comunas' => $comunas,
            'user' => $user,
            'establecimiento' => $establecimiento,
            'servers' => $servers,
        ]);
    }
}
