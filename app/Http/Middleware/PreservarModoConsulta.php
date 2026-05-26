<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo consulta — preserva el flag entre redirects y cierra la solapa al
 * terminar una operación de escritura.
 *
 * Solo actúa cuando la request entra con vista=consulta (query o body).
 * - Redirect tras GET o tras escritura con errores de validación
 *   → conserva el flag para que el usuario siga viendo todo sin menú.
 * - Redirect tras POST/PUT/PATCH/DELETE exitoso (sin errores)
 *   → reemplaza el redirect por una vista "Operación realizada" con botón
 *     "Cerrar solapa", ya que en modo consulta no tiene sentido seguir
 *     navegando un listado sin menú.
 */
class PreservarModoConsulta
{
    private const FLAG_NAME = 'vista';
    private const FLAG_VALUE = 'consulta';

    public function handle(Request $request, Closure $next)
    {
        if (!$this->modoConsulta($request)) {
            return $next($request);
        }

        // Disponible en vistas (layout, header) por si la URL del redirect
        // siguiente no se preservara por algún flujo intermedio.
        View::share('modoConsulta', true);

        $response = $next($request);

        if (!$response instanceof RedirectResponse) {
            return $response;
        }

        $metodo = strtoupper($request->method());
        $esEscritura = in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if (!$esEscritura || $this->tieneErroresDeValidacion()) {
            return $this->preservarFlagEnRedirect($response);
        }

        return $this->respuestaOperacionRealizada($request, $response);
    }

    private function modoConsulta(Request $request): bool
    {
        return $request->input(self::FLAG_NAME) === self::FLAG_VALUE;
    }

    private function tieneErroresDeValidacion(): bool
    {
        $errors = session()->get('errors');
        if (!$errors) {
            return false;
        }
        return is_object($errors) && method_exists($errors, 'any') && $errors->any();
    }

    private function preservarFlagEnRedirect(RedirectResponse $response): RedirectResponse
    {
        $url = $response->getTargetUrl();
        $parts = parse_url($url);
        if ($parts === false) {
            return $response;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (($query[self::FLAG_NAME] ?? null) === self::FLAG_VALUE) {
            return $response;
        }

        $query[self::FLAG_NAME] = self::FLAG_VALUE;
        $parts['query'] = http_build_query($query);

        $response->setTargetUrl($this->reconstruirUrl($parts));
        return $response;
    }

    private function reconstruirUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        return $scheme.$host.$port.$path.$query.$fragment;
    }

    private function respuestaOperacionRealizada(Request $request, RedirectResponse $response): Response
    {
        $mensaje = $this->mensajeDeSesion();
        $urlOriginalRedirect = $this->preservarFlagEnRedirect(clone $response)->getTargetUrl();
        $urlReferer = $request->headers->get('referer');

        return response()->view('theme.lte.modo_consulta_resultado', [
            'mensaje' => $mensaje,
            'urlContinuar' => $this->urlConFlag($urlReferer ?: $urlOriginalRedirect),
        ]);
    }

    private function mensajeDeSesion(): string
    {
        foreach (['mensaje', 'success', 'status'] as $clave) {
            $valor = session()->get($clave);
            if (is_string($valor) && trim($valor) !== '') {
                return $valor;
            }
        }
        return 'Operación realizada correctamente.';
    }

    private function urlConFlag(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query[self::FLAG_NAME] = self::FLAG_VALUE;
        $parts['query'] = http_build_query($query);
        return $this->reconstruirUrl($parts);
    }
}
