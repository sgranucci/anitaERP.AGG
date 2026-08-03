<?php

namespace App\Http\Middleware;

use App\Support\Configuracion\BitacoraAccesoWriter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra navegación en background (terminate) sin usar colas Laravel.
 *
 * El contexto va en $request->attributes: Laravel reinstancia el middleware
 * en Kernel::terminateMiddleware(), así que una propiedad de instancia se pierde.
 */
class RegistrarBitacoraAcceso
{
    private const ATTR_CONTEXTO = 'bitacora_acceso_ctx';

    public function handle(Request $request, Closure $next): Response
    {
        if (! BitacoraAccesoWriter::habilitado() || BitacoraAccesoWriter::debeExcluir($request)) {
            return $next($request);
        }

        $user = Auth::user();
        $empresas = Session::get('usuario_empresas');
        $empresaId = Session::get('empresa_id');
        if ($empresaId === null && is_array($empresas) && isset($empresas[0]['id'])) {
            $empresaId = $empresas[0]['id'];
        }

        $request->attributes->set(self::ATTR_CONTEXTO, [
            'started' => defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true),
            'usuario_id' => $user?->id ?? Session::get('usuario_id'),
            'usuario_nombre' => $user?->nombre ?? Session::get('nombre_usuario') ?? Session::get('usuario'),
            'empresa_id' => $empresaId !== null ? (int) $empresaId : null,
            'rol_id' => Session::get('rol_id') !== null ? (int) Session::get('rol_id') : null,
            'session_id' => Session::getId() ?: null,
        ]);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $contexto = $request->attributes->get(self::ATTR_CONTEXTO);
        if (! is_array($contexto)) {
            return;
        }

        // Tras login el usuario ya está autenticado; completar nombre/id si faltaba.
        if (empty($contexto['usuario_id']) && Auth::check()) {
            $user = Auth::user();
            $contexto['usuario_id'] = $user?->id;
            $contexto['usuario_nombre'] = $user?->nombre ?? ($contexto['usuario_nombre'] ?? null);
        }

        // Sin usuario: solo registrar login/logout (evitar ruido de invitados).
        if (empty($contexto['usuario_id'])) {
            $path = trim($request->path(), '/');
            $esAuth = str_contains($path, 'seguridad/login') || str_contains($path, 'seguridad/logout');
            if (! $esAuth) {
                return;
            }
        }

        BitacoraAccesoWriter::registrarDesdeRequest($request, $response, $contexto);
    }
}
