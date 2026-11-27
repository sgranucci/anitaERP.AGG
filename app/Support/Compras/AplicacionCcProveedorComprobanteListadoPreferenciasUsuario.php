<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Support\Facades\Cache;

/**
 * Grilla estándar (por usuario) del listado de comprobantes de aplicación CC de proveedores.
 * Las vistas nombradas viven en listado_vista y no pisan este cache.
 */
final class AplicacionCcProveedorComprobanteListadoPreferenciasUsuario
{
    private const CACHE_GRILLA = 'aplicacion-cc-proveedor-listado-grilla-estandar-v1';

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
        $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            AplicacionCcProveedorComprobanteListadoColumnas::RECURSO,
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
        $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            AplicacionCcProveedorComprobanteListadoColumnas::RECURSO,
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
            return AplicacionCcProveedorComprobanteListadoColumnas::normalizarVisibles($desdeRequest);
        }

        return ListadoGrillaConfigSupport::keysVisibles(self::grillaEstandar());
    }
}
