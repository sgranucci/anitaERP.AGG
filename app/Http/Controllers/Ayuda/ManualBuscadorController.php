<?php

namespace App\Http\Controllers\Ayuda;

use App\Http\Controllers\Controller;
use App\Services\Ayuda\ManualBuscadorService;
use Illuminate\Support\Facades\Auth;

class ManualBuscadorController extends Controller
{
    public function __construct(private readonly ManualBuscadorService $manual) {}

    public function index()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('ayuda.manual_buscador.index', compact('meta'));
    }
}
