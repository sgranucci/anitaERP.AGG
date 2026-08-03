<?php

namespace App\Http\Middleware;

use App\Support\Uif\ClienteUifOrigenPcSupport;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Acceso UIF: PC con PV estacionamiento, o usuario con empresas UIF asignadas.
 */
class UifPcConfigurada
{
    public function handle(Request $request, Closure $next)
    {
        try {
            ClienteUifOrigenPcSupport::assertPuedeAcceder($request);
        } catch (RuntimeException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('inicio')
                ->with('mensaje-error', $e->getMessage());
        }

        return $next($request);
    }
}
