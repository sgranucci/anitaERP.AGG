<?php

namespace App\Http\Middleware;

use App\Support\Caja\Estacionamiento\EstacionamientoModuloSupport;
use Closure;
use Illuminate\Http\Request;

class EstacionamientoHabilitado
{
    public function handle(Request $request, Closure $next)
    {
        EstacionamientoModuloSupport::assertHabilitado();

        return $next($request);
    }
}
