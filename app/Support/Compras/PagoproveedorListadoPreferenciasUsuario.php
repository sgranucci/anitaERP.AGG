<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Support\Facades\Cache;

final class PagoproveedorListadoPreferenciasUsuario
{
    private const CACHE_GRILLA = 'compras-pagoproveedor-listado-grilla-estandar-v1';

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
        $catalogo = PagoproveedorListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            PagoproveedorListadoColumnas::RECURSO,
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
        $catalogo = PagoproveedorListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            PagoproveedorListadoColumnas::RECURSO,
            $catalogo
        );

        return ListadoGrillaConfigSupport::normalizar($desdeRequest, $catalogo, $etiquetas);
    }
}
