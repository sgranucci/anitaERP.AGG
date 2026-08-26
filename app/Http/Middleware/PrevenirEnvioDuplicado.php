<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarta el segundo envío idéntico de un formulario.
 *
 * Incidente 21/ago/2026: solicitud de pago grabada 3 veces (códigos 11333/11334/11335)
 * con 4 y 5 segundos de diferencia, mismo usuario, mismo navegador, mismo payload. El
 * alta escribe en Anita y dispara el árbol de aprobación: tarda, y el operador vuelve a
 * apretar Guardar creyendo que no grabó.
 *
 * El bloqueo de pantalla (assets/js/grabacion-bloqueo-submit.js) es la primera barrera;
 * esto cubre el caso en que el navegador manda el envío igual (JS desactivado, doble
 * envío disparado antes de que el banner se pinte, reintento manual del historial).
 *
 * No aplica a: GET, pedidos AJAX/JSON (los modales de consulta repiten el mismo POST a
 * propósito), rutas de config('envio_duplicado.rutas_excluidas') y usuarios anónimos.
 */
class PrevenirEnvioDuplicado
{
    private const METODOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->debeControlar($request)) {
            return $next($request);
        }

        $clave = $this->clave($request);
        $ventana = max(1, (int) config('envio_duplicado.ventana_segundos', 12));

        try {
            $primerEnvio = Cache::add($clave, now()->toDateTimeString(), $ventana);
        } catch (\Throwable $e) {
            // Sin cache disponible no se bloquea nada: el circuito sigue como antes.
            Log::warning('envio_duplicado.cache_no_disponible', ['error' => $e->getMessage()]);

            return $next($request);
        }

        if (! $primerEnvio) {
            return $this->respuestaDuplicado($request, $clave, $ventana);
        }

        $erroresPrevios = $request->hasSession() ? $request->session()->get('errors') : null;
        $respuesta = $next($request);

        if ($this->noPersistio($request, $respuesta, $erroresPrevios)) {
            Cache::forget($clave);
        }

        return $respuesta;
    }

    private function debeControlar(Request $request): bool
    {
        if (! config('envio_duplicado.habilitado', true)) {
            return false;
        }

        if (! in_array($request->method(), self::METODOS, true)) {
            return false;
        }

        // Los modales de consulta y las APIs de pantalla reenvían el mismo POST a propósito.
        if ($request->ajax() || $request->expectsJson() || $request->wantsJson() || $request->isJson()) {
            return false;
        }

        // Solo navegaciones de formulario: el navegador pide text/html. jQuery y fetch()
        // mandan Accept */* y algunos endpoints de pantalla repiten el mismo POST adrede.
        if (! str_contains((string) $request->header('Accept', ''), 'text/html')) {
            return false;
        }

        if (! Auth::check()) {
            return false;
        }

        if ($this->estaExcluida($request)) {
            return false;
        }

        return true;
    }

    private function estaExcluida(Request $request): bool
    {
        $nombres = (array) config('envio_duplicado.rutas_nombre_excluidas', []);
        if ($nombres !== [] && $request->routeIs(...$nombres)) {
            return true;
        }

        // Reimprimir sesión: el POST es idéntico a propósito (auto=1 + botón).
        if ($request->routeIs('ejecutar_impresion_sesion')
            || str_contains($request->decodedPath(), 'impresion-sesion/ejecutar')) {
            return true;
        }

        $excluidas = (array) config('envio_duplicado.rutas_excluidas', []);
        if ($excluidas === []) {
            return false;
        }

        return $request->is(...$excluidas);
    }

    /** Mismo usuario + misma sesión + misma URL + mismo contenido = mismo envío. */
    private function clave(Request $request): string
    {
        $datos = $request->post();
        unset($datos['_token']);
        $this->ordenarRecursivo($datos);

        $archivos = array_map(
            static fn ($archivo) => $archivo?->getClientOriginalName().'|'.$archivo?->getSize(),
            Arr::flatten($request->allFiles())
        );
        sort($archivos);

        return 'envio_duplicado:'.sha1(implode('|', [
            (string) Auth::id(),
            $request->hasSession() ? $request->session()->getId() : '',
            $request->method(),
            $request->path(),
            $request->getQueryString() ?? '',
            json_encode($datos),
            implode(',', $archivos),
        ]));
    }

    private function ordenarRecursivo(array &$datos): void
    {
        ksort($datos);
        foreach ($datos as &$valor) {
            if (is_array($valor)) {
                $this->ordenarRecursivo($valor);
            }
        }
    }

    /**
     * Si el primer envío no grabó (excepción, validación fallida), se libera la clave
     * para que el operador pueda reintentar el mismo formulario sin esperar la ventana.
     */
    private function noPersistio(Request $request, Response $respuesta, $erroresPrevios): bool
    {
        if ($respuesta->getStatusCode() >= 400) {
            return true;
        }

        if (! $request->hasSession()) {
            return false;
        }

        $errores = $request->session()->get('errors');

        return $errores !== null && $errores !== $erroresPrevios;
    }

    private function respuestaDuplicado(Request $request, string $clave, int $ventana): Response
    {
        // Ventana deslizante: mientras siga golpeando, sigue bloqueado.
        Cache::put($clave, now()->toDateTimeString(), $ventana);

        Log::warning('envio_duplicado.bloqueado', [
            'usuario_id' => Auth::id(),
            'metodo' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        $mensaje = 'Se descartó un envío repetido del mismo formulario (doble click en Guardar). '
            .'El primero se está procesando o ya se grabó: revise el listado antes de volver a intentar.';

        if ($request->hasSession()) {
            return redirect()->back()->with('mensaje-error', $mensaje);
        }

        return response($mensaje, 409);
    }
}
