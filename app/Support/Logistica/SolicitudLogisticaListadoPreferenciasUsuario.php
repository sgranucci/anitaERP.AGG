<?php

declare(strict_types=1);

namespace App\Support\Logistica;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Support\Facades\Cache;

final class SolicitudLogisticaListadoPreferenciasUsuario
{
    private const CACHE_GRILLA = 'logistica-solicitud-listado-grilla-estandar-v1';

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
        $catalogo = SolicitudLogisticaListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SolicitudLogisticaListadoColumnas::RECURSO,
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
        $catalogo = SolicitudLogisticaListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SolicitudLogisticaListadoColumnas::RECURSO,
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
            return SolicitudLogisticaListadoColumnas::normalizarVisibles($desdeRequest);
        }

        return ListadoGrillaConfigSupport::keysVisibles(self::grillaEstandar());
    }
}
