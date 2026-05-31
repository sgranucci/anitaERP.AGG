<?php

namespace App\Support\Listado;

use Illuminate\Http\Request;

/**
 * Lectura consistente de parámetros GET de filtros en listados (index / export).
 */
class FiltrosListadoRequest
{
    /**
     * Valor de búsqueda enviado por el formulario.
     * Si existe filtro_valor en la request (aunque vacío), no se reutiliza busqueda legacy.
     */
    public static function valorBusqueda(Request $request, ?string $busquedaRuta = null): string
    {
        if ($request->has('filtro_valor')) {
            return trim((string) $request->input('filtro_valor', ''));
        }

        if ($request->has('busqueda')) {
            return trim((string) $request->input('busqueda', ''));
        }

        if ($busquedaRuta !== null && $busquedaRuta !== '') {
            return trim($busquedaRuta);
        }

        return '';
    }

    public static function solicitudLimpiaFiltros(Request $request): bool
    {
        return $request->boolean('filtro_limpiar');
    }
}
