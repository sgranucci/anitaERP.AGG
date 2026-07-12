<?php

namespace App\Http\Middleware;

use App\Support\Caja\Bingo\BingoModuloSupport;
use Closure;
use Illuminate\Http\Request;

class BingoHabilitado
{
    public function handle(Request $request, Closure $next)
    {
        BingoModuloSupport::assertHabilitado();

        return $next($request);
    }
}
