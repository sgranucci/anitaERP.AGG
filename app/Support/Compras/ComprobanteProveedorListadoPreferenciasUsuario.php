<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use Illuminate\Support\Facades\Cache;

final class ComprobanteProveedorListadoPreferenciasUsuario
{
    private const CACHE_GRILLA = 'comprobante-proveedor-listado-grilla-estandar-v1';

    private const CACHE_ORDEN = 'comprobante-proveedor-listado-orden-v1';

    /**
     * @param  list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>  $layout
     */
    public static function persistirGrillaEstandar(array $layout): void
    {
        Cache::forever(generaKey(self::CACHE_GRILLA), $layout);
    }

    /**
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function grillaEstandar(): array
    {
        $catalogo = ComprobanteProveedorListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ComprobanteProveedorListadoColumnas::RECURSO,
            $catalogo
        );
        $cached = cache()->get(generaKey(self::CACHE_GRILLA));
        if (is_array($cached) && $cached !== []) {
            return ListadoGrillaConfigSupport::normalizar($cached, $catalogo, $etiquetas);
        }

        return ListadoGrillaConfigSupport::layoutDefault($catalogo, $etiquetas);
    }

    /**
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function normalizarLayout(mixed $desdeRequest): array
    {
        $catalogo = ComprobanteProveedorListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ComprobanteProveedorListadoColumnas::RECURSO,
            $catalogo
        );

        return ListadoGrillaConfigSupport::normalizar($desdeRequest, $catalogo, $etiquetas);
    }

    /**
     * @param  list<string>|null  $desdeRequest
     * @return list<string>
     */
    public static function resolverColumnas(?array $desdeRequest = null): array
    {
        if (is_array($desdeRequest) && $desdeRequest !== []) {
            return ComprobanteProveedorListadoColumnas::normalizarVisibles($desdeRequest);
        }

        return ListadoGrillaConfigSupport::keysVisibles(self::grillaEstandar());
    }

    /**
     * Orden elegido en la barra (distinto del estándar fecha IVA + ID). Vacío = orden de instalación.
     *
     * @return list<array{campo: string, dir: string}>
     */
    public static function leerOrden(): array
    {
        $cached = cache()->get(generaKey(self::CACHE_ORDEN));
        if (! is_array($cached) || $cached === []) {
            return [];
        }

        return ListadoOrdenamientoSupport::normalizar(
            $cached,
            ComprobanteProveedorListadoFiltros::camposOrdenables()
        );
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     */
    public static function persistirOrden(array $orden): void
    {
        $orden = ListadoOrdenamientoSupport::normalizar(
            $orden,
            ComprobanteProveedorListadoFiltros::camposOrdenables()
        );
        if ($orden === []) {
            self::olvidarOrden();

            return;
        }

        Cache::forever(generaKey(self::CACHE_ORDEN), $orden);
    }

    public static function olvidarOrden(): void
    {
        Cache::forget(generaKey(self::CACHE_ORDEN));
    }
}
