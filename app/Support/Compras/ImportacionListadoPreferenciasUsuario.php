<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Support\Facades\Cache;

/**
 * Preferencias de grilla del listado de importaciones (por usuario).
 *
 * CACHE_GRILLA = solo la "Vista estándar" personal.
 * Las vistas nombradas viven en listado_vista.columnas_json y NO pisan este cache.
 */
final class ImportacionListadoPreferenciasUsuario
{
    /** v2: vista estándar compacta (Total=landed, sin scroll, export junto a Estado). */
    private const CACHE_GRILLA = 'importacion-listado-grilla-estandar-v2';

    private const CACHE_COLUMNAS = 'importacion-listado-columnas-v2';

    /**
     * Persiste la grilla de la Vista estándar (sin vista_id).
     *
     * @param  list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>  $layout
     */
    public static function persistirGrillaEstandar(array $layout): void
    {
        Cache::forever(generaKey(self::CACHE_GRILLA), $layout);
        self::persistirColumnas(ListadoGrillaConfigSupport::keysVisibles($layout));
    }

    /**
     * Grilla de la Vista estándar (preferencia del usuario o defaults del catálogo).
     *
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function grillaEstandar(): array
    {
        $catalogo = ImportacionListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ImportacionListadoColumnas::RECURSO,
            $catalogo
        );

        $cached = cache()->get(generaKey(self::CACHE_GRILLA));
        if (is_array($cached) && $cached !== []) {
            return ListadoGrillaConfigSupport::normalizar($cached, $catalogo, $etiquetas);
        }

        $legacy = cache()->get(generaKey(self::CACHE_COLUMNAS));
        if (is_array($legacy) && $legacy !== []) {
            return ListadoGrillaConfigSupport::normalizar($legacy, $catalogo, $etiquetas);
        }

        return ListadoGrillaConfigSupport::layoutDefault($catalogo, $etiquetas);
    }

    /**
     * Normaliza un layout crudo (request / vista) sin leer ni escribir cache.
     *
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function normalizarLayout(mixed $desdeRequest): array
    {
        $catalogo = ImportacionListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ImportacionListadoColumnas::RECURSO,
            $catalogo
        );

        return ListadoGrillaConfigSupport::normalizar($desdeRequest, $catalogo, $etiquetas);
    }

    /**
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function resolverGrilla(mixed $desdeRequest = null): array
    {
        if ($desdeRequest !== null && $desdeRequest !== [] && $desdeRequest !== '') {
            return self::normalizarLayout($desdeRequest);
        }

        return self::grillaEstandar();
    }

    /**
     * @param  list<string>  $columnas
     */
    public static function persistirColumnas(array $columnas): void
    {
        Cache::forever(generaKey(self::CACHE_COLUMNAS), ImportacionListadoColumnas::normalizarVisibles($columnas));
    }

    /**
     * Columnas visibles en el orden pedido (request) o las de la Vista estándar.
     *
     * @param  list<string>|null  $desdeRequest
     * @return list<string>
     */
    public static function resolverColumnas(?array $desdeRequest = null): array
    {
        if (is_array($desdeRequest) && $desdeRequest !== []) {
            return ImportacionListadoColumnas::normalizarVisibles($desdeRequest);
        }

        return ListadoGrillaConfigSupport::keysVisibles(self::grillaEstandar());
    }
}
