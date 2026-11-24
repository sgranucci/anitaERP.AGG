<?php

namespace App\Support\Listado;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Trait para controllers de index con memoria de filtros en sesión.
 *
 * El controller define SESSION_FILTROS (o getSessionFiltrosClave()) y la ruta de index.
 */
trait RecuerdaFiltrosListado
{
    protected function sessionFiltrosClave(): string
    {
        if (defined('static::SESSION_FILTROS')) {
            return (string) constant('static::SESSION_FILTROS');
        }

        throw new \LogicException(static::class.' debe definir SESSION_FILTROS o sobrescribir sessionFiltrosClave().');
    }

    /**
     * Si hay limpiar o hay que restaurar memoria, devuelve el redirect; si no, null.
     *
     * @param  array<string, mixed>  $defaultsTrasLimpiar  Query al limpiar (p. ej. empresa_todas=1)
     * @param  callable(): string  $rutaIndex  Nombre de ruta del index
     */
    protected function redirigirMemoriaOLimpiar(
        Request $request,
        string $rutaIndex,
        array $defaultsTrasLimpiar = [],
        bool $forzarEstandar = false,
        ?callable $alLimpiar = null
    ): ?RedirectResponse {
        if (ListadoFiltrosMemoriaSupport::solicitudLimpia($request)) {
            ListadoFiltrosMemoriaSupport::olvidar($this->sessionFiltrosClave());
            if ($alLimpiar !== null) {
                $alLimpiar();
            }

            return redirect()->route($rutaIndex, $defaultsTrasLimpiar);
        }

        if (! ListadoFiltrosMemoriaSupport::requestTraeFiltros($request) && ! $forzarEstandar) {
            $guardados = ListadoFiltrosMemoriaSupport::restaurar($this->sessionFiltrosClave());
            if ($guardados !== [] && ListadoFiltrosMemoriaSupport::queryTraeFiltros($guardados)) {
                return redirect()->route($rutaIndex, $guardados);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filtrosQuery
     * @return array<string, mixed>
     */
    protected function recordarFiltrosListadoSiCorresponde(Request $request, array $filtrosQuery): array
    {
        $page = (int) $request->input('page', 0);
        if ($page > 1) {
            $filtrosQuery['page'] = $page;
        }
        if (ListadoFiltrosMemoriaSupport::requestTraeFiltros($request)) {
            ListadoFiltrosMemoriaSupport::persistir($this->sessionFiltrosClave(), $filtrosQuery);
        }

        return $filtrosQuery;
    }
}
