<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo_Proveedor;
use Illuminate\Support\Facades\DB;

class ArticuloProveedorPrecioListaSupport
{
    /**
     * @return array<string, mixed>|null
     */
    public static function precioVigente(
        int $articuloId,
        int $proveedorId,
        ?int $listaprecioProveedorId = null,
        ?string $fechaRef = null
    ): ?array {
        if ($articuloId <= 0 || $proveedorId <= 0) {
            return null;
        }

        $fechaRef = $fechaRef ?? date('Y-m-d');

        $subMaxFv = DB::table('listaprecio_proveedor_articulo')
            ->select('listaprecio_proveedor_id', DB::raw('MAX(fechavigencia) as max_fv'))
            ->where('articulo_id', $articuloId)
            ->whereDate('fechavigencia', '<=', $fechaRef)
            ->groupBy('listaprecio_proveedor_id');

        $lineIdsQuery = DB::table('listaprecio_proveedor_articulo as lpa')
            ->joinSub($subMaxFv, 'mx', function ($join) {
                $join->on('lpa.listaprecio_proveedor_id', '=', 'mx.listaprecio_proveedor_id')
                    ->on('lpa.fechavigencia', '=', 'mx.max_fv');
            })
            ->where('lpa.articulo_id', $articuloId)
            ->groupBy('lpa.listaprecio_proveedor_id')
            ->select(DB::raw('MAX(lpa.id) as id'));

        $lineIds = $lineIdsQuery->pluck('id')->filter()->values()->all();
        if ($lineIds === []) {
            return null;
        }

        $q = DB::table('listaprecio_proveedor_articulo as lpa')
            ->join('listaprecio_proveedor as lp', 'lp.id', '=', 'lpa.listaprecio_proveedor_id')
            ->leftJoin('moneda as mon', 'mon.id', '=', 'lp.moneda_id')
            ->whereIn('lpa.id', $lineIds)
            ->where('lp.estado', 'ACTIVA')
            ->where('lp.proveedor_id', $proveedorId);

        if ($listaprecioProveedorId !== null && $listaprecioProveedorId > 0) {
            $q->where('lp.id', $listaprecioProveedorId);
        }

        $r = $q->select([
            'lpa.id as linea_lista_id',
            'lp.id as lista_id',
            'lp.nombre as lista_nombre',
            'mon.id as moneda_id',
            'mon.abreviatura as moneda_abreviatura',
            'lpa.precio',
            'lpa.descuento',
            'lpa.fechavigencia as linea_fechavigencia',
            'lpa.codigo_articulo_proveedor',
        ])
            ->orderByDesc('lp.fecha')
            ->orderByDesc('lp.id')
            ->first();

        if (! $r) {
            return null;
        }

        $precio = (float) $r->precio;
        $dto = (float) $r->descuento;
        $precioNeto = round($precio * max(0.0, 1 - ($dto / 100.0)), 6);

        return [
            'precio' => $precioNeto,
            'precio_bruto' => $precio,
            'descuento_pct' => $dto,
            'moneda_id' => $r->moneda_id ? (int) $r->moneda_id : null,
            'moneda_abreviatura' => (string) ($r->moneda_abreviatura ?? ''),
            'listaprecio_proveedor_id' => (int) $r->lista_id,
            'lista_nombre' => (string) ($r->lista_nombre ?? ''),
            'linea_lista_id' => (int) $r->linea_lista_id,
            'fechavigencia' => $r->linea_fechavigencia ? substr((string) $r->linea_fechavigencia, 0, 10) : '',
            'codigo_articulo_proveedor' => (string) ($r->codigo_articulo_proveedor ?? ''),
        ];
    }

    public static function enriquecerLinea(Articulo_Proveedor $linea, ?string $fechaRef = null): Articulo_Proveedor
    {
        $articuloId = (int) ($linea->articulo_id ?? 0);
        $proveedorId = (int) ($linea->proveedor_id ?? 0);

        $vigente = ($articuloId > 0 && $proveedorId > 0)
            ? self::precioVigente($articuloId, $proveedorId, null, $fechaRef)
            : null;

        $linea->setAttribute('precio_vigente', $vigente['precio'] ?? null);
        $linea->setAttribute('moneda_vigente_id', $vigente['moneda_id'] ?? null);
        $linea->setAttribute('moneda_vigente_abreviatura', $vigente['moneda_abreviatura'] ?? '');
        $linea->setAttribute('fechavigencia_lista', $vigente['fechavigencia'] ?? '');
        $linea->setAttribute('lista_nombre_resuelta', $vigente['lista_nombre'] ?? '');
        $linea->setAttribute('listaprecio_proveedor_id_resuelto', $vigente['listaprecio_proveedor_id'] ?? null);
        $linea->setAttribute('tiene_precio_vigente', $vigente !== null);

        $codigoEfectivo = ArticuloProveedorCodigoSyncSupport::codigoEfectivoParaLinea($linea, $fechaRef);
        if ($codigoEfectivo !== null && self::normalizarCodigo($linea->codigo_articulo_proveedor) === null) {
            $linea->setAttribute('codigo_articulo_proveedor', $codigoEfectivo);
            $linea->setAttribute('codigo_articulo_proveedor_heredado_lista', true);
        }

        return $linea;
    }

    private static function normalizarCodigo(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }

        $codigo = trim($codigo);

        return $codigo === '' ? null : $codigo;
    }
}
