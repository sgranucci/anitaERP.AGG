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
        try {
            if (! BitacoraAccesoWriter::habilitado() || BitacoraAccesoWriter::debeExcluir($request)) {
                return $next($request);
            }

            $user = Auth::user();
            $empresas = Session::get('usuario_empresas');
            $empresaId = Session::get('empresa_id');
            if ($empresaId === null) {
                $empresaId = $this->resolverEmpresaIdDesdeSession($empresas);
            }

            $request->attributes->set(self::ATTR_CONTEXTO, [
                'started' => defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true),
                'usuario_id' => $user?->id ?? Session::get('usuario_id'),
                'usuario_nombre' => $user?->nombre ?? Session::get('nombre_usuario') ?? Session::get('usuario'),
                'empresa_id' => $empresaId !== null ? (int) $empresaId : null,
                'rol_id' => Session::get('rol_id') !== null ? (int) Session::get('rol_id') : null,
                'session_id' => Session::getId() ?: null,
            ]);
        } catch (\Throwable) {
            // La bitácora no debe tumbar la navegación.
        }

        return $next($request);
    }

    /**
     * @param  mixed  $empresas
     */
    private function resolverEmpresaIdDesdeSession($empresas): mixed
    {
        if (! is_array($empresas) || $empresas === []) {
            return null;
        }

        $primera = $empresas[array_key_first($empresas)];
        if (is_array($primera)) {
            return $primera['id'] ?? null;
        }
        if (is_object($primera)) {
            return $primera->id ?? null;
        }

        return null;
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
