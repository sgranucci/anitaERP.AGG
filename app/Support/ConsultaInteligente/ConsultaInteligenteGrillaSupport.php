<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Support\Caja\ChequeListadoPreferenciasUsuario;
use App\Support\Caja\IngresoEgresoListadoPreferenciasUsuario;
use App\Support\Compras\ComprobanteProveedorListadoPreferenciasUsuario;
use App\Support\Compras\PagoproveedorListadoPreferenciasUsuario;
use App\Support\Compras\ProveedorListadoPreferenciasUsuario;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Stock\ArticuloListadoPreferenciasUsuario;
use App\Support\Ventas\ClienteListadoPreferenciasUsuario;

/**
 * Layout / catálogo de columnas del explorador CI (Diseñar vista).
 */
final class ConsultaInteligenteGrillaSupport
{
    /**
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function layoutEstandar(EntidadSemantica $entidad): array
    {
        $pref = self::preferencias($entidad->clave());
        if ($pref !== null) {
            return $pref::grillaEstandar();
        }

        return self::layoutDesdeEntidad($entidad, null);
    }

    /**
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    public static function normalizar(EntidadSemantica $entidad, mixed $raw): array
    {
        $pref = self::preferencias($entidad->clave());
        if ($pref !== null) {
            return $pref::normalizarLayout($raw);
        }

        return self::layoutDesdeEntidad($entidad, $raw);
    }

    /**
     * @return list<string>
     */
    public static function keysVisiblesDesde(EntidadSemantica $entidad, mixed $columnasJson): array
    {
        if ($columnasJson === null || $columnasJson === [] || $columnasJson === '') {
            return $entidad->columnasVisibles();
        }
        $layout = self::normalizar($entidad, $columnasJson);
        $keys = ListadoGrillaConfigSupport::keysVisibles($layout);

        return $keys !== [] ? $keys : $entidad->columnasVisibles();
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasDesde(EntidadSemantica $entidad, mixed $columnasJson = null): array
    {
        $layout = $columnasJson === null || $columnasJson === []
            ? self::layoutEstandar($entidad)
            : self::normalizar($entidad, $columnasJson);

        return ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
    }

    /**
     * @return class-string|null
     */
    private static function preferencias(string $clave): ?string
    {
        return match ($clave) {
            'cheque' => ChequeListadoPreferenciasUsuario::class,
            'ingresoegreso' => IngresoEgresoListadoPreferenciasUsuario::class,
            'comprobante_proveedor' => ComprobanteProveedorListadoPreferenciasUsuario::class,
            'pagoproveedor' => PagoproveedorListadoPreferenciasUsuario::class,
            'proveedor' => ProveedorListadoPreferenciasUsuario::class,
            'cliente' => ClienteListadoPreferenciasUsuario::class,
            'articulo' => ArticuloListadoPreferenciasUsuario::class,
            default => null,
        };
    }

    /**
     * Fallback para entidades sin Preferencias (OC, requisición, cobranza, …).
     *
     * @return list<array{key: string, titulo: string, visible: bool, ancho: int, alinea: string, orden: int}>
     */
    private static function layoutDesdeEntidad(EntidadSemantica $entidad, mixed $raw): array
    {
        $etiquetas = $entidad->etiquetasColumnas();
        $visiblesDefault = array_flip($entidad->columnasVisibles());
        $catalogo = [];
        foreach ($etiquetas as $key => $label) {
            $catalogo[$key] = [
                'label' => $label,
                'default' => isset($visiblesDefault[$key]),
            ];
        }
        foreach ($entidad->camposQbe() as $key => $meta) {
            if (! isset($catalogo[$key])) {
                $catalogo[$key] = [
                    'label' => (string) ($meta['label'] ?? $key),
                    'default' => false,
                ];
            }
        }

        return ListadoGrillaConfigSupport::normalizar($raw, $catalogo, $etiquetas);
    }
}
