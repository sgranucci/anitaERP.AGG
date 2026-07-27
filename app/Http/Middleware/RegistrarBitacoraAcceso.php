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
 */
class RegistrarBitacoraAcceso
{
    /** @var array<string, mixed>|null */
    private ?array $contexto = null;

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

        $this->contexto = [
            'started' => defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true),
            'usuario_id' => $user?->id ?? Session::get('usuario_id'),
            'usuario_nombre' => $user?->nombre ?? Session::get('nombre_usuario') ?? Session::get('usuario'),
            'empresa_id' => $empresaId !== null ? (int) $empresaId : null,
            'rol_id' => Session::get('rol_id') !== null ? (int) Session::get('rol_id') : null,
            'session_id' => Session::getId() ?: null,
        ];

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->contexto === null) {
            return;
        }

        // Tras login el usuario ya está autenticado; completar nombre/id si faltaba.
        if (empty($this->contexto['usuario_id']) && Auth::check()) {
            $user = Auth::user();
            $this->contexto['usuario_id'] = $user?->id;
            $this->contexto['usuario_nombre'] = $user?->nombre ?? $this->contexto['usuario_nombre'];
        }

        // Sin usuario: solo registrar login/logout (evitar ruido de invitados).
        if (empty($this->contexto['usuario_id'])) {
            $path = trim($request->path(), '/');
            $esAuth = str_contains($path, 'seguridad/login') || str_contains($path, 'seguridad/logout');
            if (! $esAuth) {
                return;
            }
        }

        BitacoraAccesoWriter::registrarDesdeRequest($request, $response, $this->contexto);
    }
}
