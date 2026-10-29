<?php

declare(strict_types=1);

namespace App\Support\Produccion;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;

/**
 * Grilla estándar del listado de movimientos de OT.
 * Las vistas nombradas viven en listado_vista.
 */
final class MovimientoOrdentrabajoListadoPreferenciasUsuario
{
    private const CACHE_GRILLA = 'movimiento-ot-listado-grilla-estandar-v1';

    /**
     * @param  list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>  $layout
     */
    public static function persistirGrillaEstandar(array $layout): void
    {
        cache()->forever(generaKey(self::CACHE_GRILLA), $layout);
    }

    /**
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function grillaEstandar(): array
    {
        $catalogo = MovimientoOrdentrabajoListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            MovimientoOrdentrabajoListadoColumnas::RECURSO,
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
        $catalogo = MovimientoOrdentrabajoListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            MovimientoOrdentrabajoListadoColumnas::RECURSO,
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
            return MovimientoOrdentrabajoListadoColumnas::normalizarVisibles($desdeRequest);
        }

        return ListadoGrillaConfigSupport::keysVisibles(self::grillaEstandar());
    }
}
