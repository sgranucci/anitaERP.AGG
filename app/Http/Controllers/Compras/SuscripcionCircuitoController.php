<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * El circuito documentado vive en el manual / Centro de ayuda.
 * La URL antigua redirige para no romper favoritos.
 */
class SuscripcionCircuitoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-suscripcion');

        return redirect()->route('manual_suscripciones');
    }
}
