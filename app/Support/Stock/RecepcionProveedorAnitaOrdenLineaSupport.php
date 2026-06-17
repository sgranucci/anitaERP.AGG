<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Services\Compras\OrdencompraAnitaSyncService;

/**
 * Clave Anita de línea OC: penvp_nro_interno (único en pendmovp).
 * recv_orden en recepmov es secuencial por recepción (campo orden); pendmovp se actualiza por nro_interno.
 */
final class RecepcionProveedorAnitaOrdenLineaSupport
{
    /**
     * Sincroniza nro_interno/orden desde Anita y devuelve recv_orden (1..n) por línea de recepción.
     *
     * @return array<int, int> recepcion_proveedor_articulo.id => recv_orden Anita
     */
    public static function prepararOrdenesAntesDeSincronizarAnita(
        Recepcion_Proveedor $recepcion,
        OrdencompraAnitaSyncService $ordencompraAnitaSync
    ): array {
        $oc = $recepcion->ordencompras;
        if ($oc !== null) {
            $ordencompraAnitaSync->reconciliarLineasOcDesdeAnita((int) $oc->numeroordencompra);
            $oc->unsetRelation('ordencompra_articulos');
            $oc->load('ordencompra_articulos');
            self::alinearLineasRecepcionConOc($recepcion);
        }

        $recepcion->unsetRelation('recepcion_proveedor_articulos');
        $recepcion->load('recepcion_proveedor_articulos.articulos');

        return self::ordenRecepmovPorLineaId($recepcion);
    }

    /**
     * recv_orden único: secuencia de la recepción (no penvp_orden, que puede repetirse en OC legacy).
     *
     * @return array<int, int>
     */
    public static function ordenRecepmovPorLineaId(Recepcion_Proveedor $recepcion): array
    {
        $mapa = [];
        foreach ($recepcion->recepcion_proveedor_articulos->sortBy([['orden', 'asc'], ['id', 'asc']]) as $linea) {
            $orden = (int) ($linea->orden ?? 0);
            $mapa[(int) $linea->id] = $orden > 0 ? $orden : (count($mapa) + 1);
        }

        return $mapa;
    }

    public static function ordenAnitaLinea(Recepcion_Proveedor_Articulo $linea, array $ordenesPorLineaId): int
    {
        $lineaId = (int) $linea->id;

        return (int) ($ordenesPorLineaId[$lineaId] ?? $linea->orden ?? 1);
    }

    public static function nroInternoLinea(Recepcion_Proveedor_Articulo $linea): int
    {
        $nro = (int) ($linea->penvp_nro_interno ?? 0);
        if ($nro > 0) {
            return $nro;
        }

        $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
        if ($ocArtId <= 0) {
            $ocArtId = (int) ($linea->ordencompra_articulo_sustituido_id ?? 0);
        }
        if ($ocArtId <= 0) {
            return 0;
        }

        $ocArt = Ordencompra_Articulo::query()->find($ocArtId);

        return (int) ($ocArt->penvp_nro_interno ?? 0);
    }

    public static function penvpOrdenLinea(Recepcion_Proveedor_Articulo $linea): int
    {
        $orden = (int) ($linea->penvp_orden ?? 0);
        if ($orden > 0) {
            return $orden;
        }

        $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
        if ($ocArtId <= 0) {
            $ocArtId = (int) ($linea->ordencompra_articulo_sustituido_id ?? 0);
        }
        if ($ocArtId <= 0) {
            return 0;
        }

        $ocArt = Ordencompra_Articulo::query()->find($ocArtId);

        return (int) ($ocArt->penvp_orden ?? 0);
    }

    public static function aplicaPendmovp(Recepcion_Proveedor_Articulo $linea): bool
    {
        return (string) ($linea->tipo_linea ?? '') !== RecepcionProveedorDiferenciaSupport::TIPO_EXTRA;
    }

    public static function alinearLineasRecepcionConOc(Recepcion_Proveedor $recepcion): void
    {
        $recepcion->loadMissing([
            'recepcion_proveedor_articulos.articulos',
            'ordencompras.ordencompra_articulos',
        ]);
        $recepcion->ordencompras?->unsetRelation('ordencompra_articulos');
        $recepcion->ordencompras?->load('ordencompra_articulos');

        $datosPorOcArt = [];
        foreach ($recepcion->ordencompras?->ordencompra_articulos ?? [] as $ocArt) {
            $datosPorOcArt[(int) $ocArt->id] = [
                'penvp_orden' => (int) ($ocArt->penvp_orden ?? 0),
                'penvp_nro_interno' => (int) ($ocArt->penvp_nro_interno ?? 0),
            ];
        }

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if ((string) ($linea->tipo_linea ?? '') === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
                continue;
            }

            $fuenteId = (int) ($linea->ordencompra_articulo_id ?? 0);
            if ($fuenteId <= 0) {
                $fuenteId = (int) ($linea->ordencompra_articulo_sustituido_id ?? 0);
            }
            if ($fuenteId <= 0 || ! isset($datosPorOcArt[$fuenteId])) {
                continue;
            }

            $datos = $datosPorOcArt[$fuenteId];
            $cambios = [];
            if ($datos['penvp_orden'] > 0 && (int) ($linea->penvp_orden ?? 0) !== $datos['penvp_orden']) {
                $cambios['penvp_orden'] = $datos['penvp_orden'];
            }
            if ($datos['penvp_nro_interno'] > 0 && (int) ($linea->penvp_nro_interno ?? 0) !== $datos['penvp_nro_interno']) {
                $cambios['penvp_nro_interno'] = $datos['penvp_nro_interno'];
            }
            if ($cambios !== []) {
                $linea->update($cambios);
                foreach ($cambios as $k => $v) {
                    $linea->{$k} = $v;
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function normalizarPenvpOrdenEnItems(array $items, array $datosPorOcArticulo): array
    {
        $usadosInterno = [];
        $maxOrden = 0;
        foreach ($datosPorOcArticulo as $datos) {
            if (is_array($datos)) {
                $maxOrden = max($maxOrden, (int) ($datos['penvp_orden'] ?? 0));
            } else {
                $maxOrden = max($maxOrden, (int) $datos);
            }
        }

        $siguienteOrden = $maxOrden;
        $normalizados = [];

        foreach ($items as $item) {
            $tipo = (string) ($item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC);
            $ocArtId = (int) ($item['ordencompra_articulo_id'] ?? 0);
            $esExtra = $tipo === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA
                || ($ocArtId <= 0 && (int) ($item['ordencompra_articulo_sustituido_id'] ?? 0) <= 0
                    && $tipo !== RecepcionProveedorDiferenciaSupport::TIPO_SUSTITUTO);

            if (! $esExtra) {
                $datos = self::resolverDatosOcLinea($item, $datosPorOcArticulo);
                if ($datos['penvp_nro_interno'] > 0) {
                    $item['penvp_nro_interno'] = $datos['penvp_nro_interno'];
                    $usadosInterno[$datos['penvp_nro_interno']] = true;
                }
                if ($datos['penvp_orden'] > 0) {
                    $item['penvp_orden'] = $datos['penvp_orden'];
                }
            } else {
                $penvpOrden = (int) ($item['penvp_orden'] ?? 0);
                if ($penvpOrden <= 0) {
                    do {
                        $siguienteOrden++;
                    } while (isset($usadosInterno[$siguienteOrden]));
                    $penvpOrden = $siguienteOrden;
                }
                $item['penvp_orden'] = $penvpOrden;
            }

            $normalizados[] = $item;
        }

        return $normalizados;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array{penvp_orden: int, penvp_nro_interno: int}|int>  $datosPorOcArticulo
     * @return array{penvp_orden: int, penvp_nro_interno: int}
     */
    private static function resolverDatosOcLinea(array $item, array $datosPorOcArticulo): array
    {
        $sustituidoId = (int) ($item['ordencompra_articulo_sustituido_id'] ?? 0);
        $ocArtId = (int) ($item['ordencompra_articulo_id'] ?? 0);
        $fuenteId = $ocArtId > 0 ? $ocArtId : $sustituidoId;

        $penvpOrden = (int) ($item['penvp_orden'] ?? 0);
        $nroInterno = (int) ($item['penvp_nro_interno'] ?? 0);

        if ($fuenteId > 0 && isset($datosPorOcArticulo[$fuenteId])) {
            $raw = $datosPorOcArticulo[$fuenteId];
            if (is_array($raw)) {
                if ($penvpOrden <= 0) {
                    $penvpOrden = (int) ($raw['penvp_orden'] ?? 0);
                }
                if ($nroInterno <= 0) {
                    $nroInterno = (int) ($raw['penvp_nro_interno'] ?? 0);
                }
            } elseif ($penvpOrden <= 0) {
                $penvpOrden = (int) $raw;
            }
        }

        return [
            'penvp_orden' => $penvpOrden,
            'penvp_nro_interno' => $nroInterno,
        ];
    }
}
