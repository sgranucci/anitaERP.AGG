<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Support\Facades\Cache;

/**
 * Preferencias de grilla del listado de cheques (por usuario).
 *
 * CACHE_GRILLA = solo la "Vista estándar" personal.
 * Las vistas nombradas viven en listado_vista.columnas_json y NO pisan este cache.
 */
final class ChequeListadoPreferenciasUsuario
{
    private const CACHE_GRILLA = 'cheque-listado-grilla-estandar-v1';

    private const CACHE_COLUMNAS = 'cheque-listado-columnas';

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
     * @deprecated usar persistirGrillaEstandar
     *
     * @param  list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>  $layout
     */
    public static function persistirGrilla(array $layout): void
    {
        self::persistirGrillaEstandar($layout);
    }

    /**
     * Grilla de la Vista estándar (preferencia usuario o defaults del catálogo).
     *
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function grillaEstandar(): array
    {
        $catalogo = ChequeListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ChequeListadoColumnas::RECURSO,
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
     * @param  mixed  $desdeRequest
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function normalizarLayout(mixed $desdeRequest): array
    {
        $catalogo = ChequeListadoColumnas::catalogoActivo();
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ChequeListadoColumnas::RECURSO,
            $catalogo
        );

        return ListadoGrillaConfigSupport::normalizar($desdeRequest, $catalogo, $etiquetas);
    }

    /**
     * @param  mixed  $desdeRequest
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
        $normalizadas = ChequeListadoColumnas::normalizarVisibles($columnas);
        Cache::forever(generaKey(self::CACHE_COLUMNAS), $normalizadas);
    }

    /**
     * @param  list<string>|null  $desdeRequest
     * @return list<string>
     */
    public static function resolverColumnas(?array $desdeRequest = null): array
    {
        if (is_array($desdeRequest) && $desdeRequest !== []) {
            return ChequeListadoColumnas::normalizarVisibles($desdeRequest);
        }

        return ListadoGrillaConfigSupport::keysVisibles(self::grillaEstandar());
    }
}
