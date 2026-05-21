<?php

namespace App\Http\Controllers;

use App\Support\AyudaManuales;
use Illuminate\Support\Facades\Auth;

class AyudaController extends Controller
{
    /**
     * Índice de manuales por módulo (bajadas y enlaces al manual completo).
     */
    public function index()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return view('ayuda.index', [
            'manuales' => AyudaManuales::catalogo(),
        ]);
    }
}
