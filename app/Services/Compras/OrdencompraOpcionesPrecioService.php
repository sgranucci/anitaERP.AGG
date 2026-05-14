<?php

namespace App\Services\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Compras\Requisicion_Presupuesto;
use App\Models\Compras\Requisicion_Presupuesto_Articulo;
use Illuminate\Support\Facades\DB;

/**
 * Opciones de precio para armar una OC desde requisición: lista proveedor, presupuesto (activo/elegido), precio de requisición.
 */
class OrdencompraOpcionesPrecioService
{
    public const ORIGEN_LISTA = 'LISTA_PROVEEDOR';

    public const ORIGEN_PRESUPUESTO = 'PRESUPUESTO';

    public const ORIGEN_REQUISICION = 'REQUISICION';

    /**
     * Los parámetros de condiciones se conservan en la firma por compatibilidad con la ruta HTTP; ya no filtran el listado de presupuestos.
     *
     * @return array<string, mixed>
     */
    public function opcionesLinea(
        int $requisicionId,
        int $requisicionArticuloId,
        int $articuloId,
        ?int $proveedorId,
        ?int $condicioncompraId,
        ?int $condicionentregaId,
        ?int $condicionpagoId,
        string $fechaRef
    ): array {
        $ra = Requisicion_Articulo::query()
            ->where('id', $requisicionArticuloId)
            ->where('requisicion_id', $requisicionId)
            ->where('articulo_id', $articuloId)
            ->with(['monedas', 'articulos'])
            ->first();

        if (! $ra) {
            return ['message' => 'Línea de requisición no encontrada o no coincide con el artículo.'];
        }

        $reqCab = Requisicion::query()->select('id', 'proveedor_id')->find($requisicionId);
        $provReq = (int) (($reqCab !== null ? $reqCab->proveedor_id : null) ?? 0);

        $opcionRequisicion = [
            'origen' => self::ORIGEN_REQUISICION,
            'precio' => (float) $ra->precio,
            'moneda_id' => (int) $ra->moneda_id,
            'etiqueta' => 'Precio cargado en la requisición',
            'ref_id' => (int) $ra->id,
            'detalle' => 'Requisición línea #'.$ra->id,
            'proveedor_id' => $provReq,
            'condicioncompra_id' => 0,
            'condicionentrega_id' => 0,
            'condicionpago_id' => 0,
        ];

        $filasLista = [];
        if ($proveedorId !== null && $proveedorId > 0 && $articuloId > 0) {
            $filasLista = $this->filasListaPrecioProveedorArticulo($articuloId, $proveedorId, $fechaRef);
        }

        $presupuestos = $this->listarPresupuestosVisiblesParaLineaRequisicion(
            $requisicionId,
            $requisicionArticuloId,
            (int) $ra->moneda_id
        );

        $art = $ra->articulos;

        return [
            'articulo' => [
                'id' => $articuloId,
                'sku' => $art ? (string) ($art->sku ?? '') : '',
                'descripcion' => $art ? (string) ($art->descripcion ?? '') : '',
            ],
            'requisicion_articulo_id' => $requisicionArticuloId,
            'opcion_requisicion' => $opcionRequisicion,
            'opciones_lista' => $filasLista,
            'opciones_presupuesto' => $presupuestos,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filasListaPrecioProveedorArticulo(int $articuloId, int $proveedorId, string $fechaRef): array
    {
        $subMaxFv = DB::table('listaprecio_proveedor_articulo')
            ->select('listaprecio_proveedor_id', DB::raw('MAX(fechavigencia) as max_fv'))
            ->where('articulo_id', $articuloId)
            ->whereDate('fechavigencia', '<=', $fechaRef)
            ->groupBy('listaprecio_proveedor_id');

        $lineIds = DB::table('listaprecio_proveedor_articulo as lpa')
            ->joinSub($subMaxFv, 'mx', function ($join) {
                $join->on('lpa.listaprecio_proveedor_id', '=', 'mx.listaprecio_proveedor_id')
                    ->on('lpa.fechavigencia', '=', 'mx.max_fv');
            })
            ->where('lpa.articulo_id', $articuloId)
            ->groupBy('lpa.listaprecio_proveedor_id')
            ->select(DB::raw('MAX(lpa.id) as id'))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if ($lineIds === []) {
            return [];
        }

        $q = DB::table('listaprecio_proveedor_articulo as lpa')
            ->join('listaprecio_proveedor as lp', 'lp.id', '=', 'lpa.listaprecio_proveedor_id')
            ->leftJoin('proveedor as prov', 'prov.id', '=', 'lp.proveedor_id')
            ->leftJoin('moneda as mon', 'mon.id', '=', 'lp.moneda_id')
            ->whereIn('lpa.id', $lineIds)
            ->where('lp.estado', 'ACTIVA')
            ->where('lp.proveedor_id', $proveedorId)
            ->select([
                'lpa.id as linea_lista_id',
                'lp.id as lista_id',
                'lp.nombre as lista_nombre',
                'mon.id as moneda_id',
                'mon.abreviatura as moneda_abreviatura',
                'lpa.precio',
                'lpa.descuento',
                'lpa.fechavigencia as linea_fechavigencia',
            ])
            ->orderByDesc('lp.fecha')
            ->orderByDesc('lp.id');

        $salida = [];
        foreach ($q->get() as $r) {
            $precio = (float) $r->precio;
            $dto = (float) $r->descuento;
            $factor = max(0.0, 1 - ($dto / 100.0));
            $precioNeto = round($precio * $factor, 6);
            $salida[] = [
                'origen' => self::ORIGEN_LISTA,
                'linea_lista_id' => (int) $r->linea_lista_id,
                'lista_id' => (int) $r->lista_id,
                'lista_nombre' => (string) $r->lista_nombre,
                'precio' => $precioNeto,
                'precio_bruto' => $precio,
                'descuento_pct' => $dto,
                'moneda_id' => $r->moneda_id ? (int) $r->moneda_id : null,
                'moneda_abreviatura' => (string) ($r->moneda_abreviatura ?? ''),
                'fechavigencia' => $r->linea_fechavigencia ? substr((string) $r->linea_fechavigencia, 0, 10) : '',
                'etiqueta' => 'Lista proveedor: '.($r->lista_nombre ?? '').' (vig. '.substr((string) $r->linea_fechavigencia, 0, 10).')',
                'ref_id' => (int) $r->linea_lista_id,
                'proveedor_id' => $proveedorId,
                'condicioncompra_id' => 0,
                'condicionentrega_id' => 0,
                'condicionpago_id' => 0,
            ];
        }

        return $salida;
    }

    private function nombreEstadoPresupuestoPorValor(string $valorLetra): string
    {
        $idx = array_search($valorLetra, array_column(Requisicion_Presupuesto::$enumEstado, 'valor'), true);
        if ($idx === false) {
            return 'ACTIVO';
        }

        return (string) (Requisicion_Presupuesto::$enumEstado[$idx]['nombre'] ?? 'ACTIVO');
    }

    /**
     * Todos los presupuestos activos o elegidos de la requisición que cotizan la línea (sin filtrar por proveedor ni condiciones).
     * La validación al elegir corresponde al cliente (orden de compra).
     *
     * @return list<array<string, mixed>>
     */
    private function listarPresupuestosVisiblesParaLineaRequisicion(
        int $requisicionId,
        int $requisicionArticuloId,
        int $monedaLineaRequisicionId
    ): array {
        $activo = $this->nombreEstadoPresupuestoPorValor('A');
        $elegido = $this->nombreEstadoPresupuestoPorValor('E');

        $presupuestos = Requisicion_Presupuesto::query()
            ->where('requisicion_id', $requisicionId)
            ->whereIn('estado', [$activo, $elegido])
            ->with('proveedores')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $salida = [];
        foreach ($presupuestos as $pres) {
            $lin = Requisicion_Presupuesto_Articulo::query()
                ->where('requisicion_presupuesto_id', $pres->id)
                ->where('requisicion_articulo_id', $requisicionArticuloId)
                ->first();
            if (! $lin) {
                continue;
            }
            $proveedor = $pres->proveedores;
            $estadoPres = (string) ($pres->estado ?? '');
            $estLabel = ($estadoPres === $elegido) ? 'elegido' : 'activo';
            $nomProv = $proveedor ? (string) ($proveedor->nombre ?? '') : 'Sin proveedor';
            $salida[] = [
                'origen' => self::ORIGEN_PRESUPUESTO,
                'presupuesto_id' => (int) $pres->id,
                'precio' => (float) $lin->precio_unitario,
                'moneda_id' => $monedaLineaRequisicionId,
                'etiqueta' => 'Presupuesto #'.$pres->id.' · '.$nomProv.' ('.$estLabel.')',
                'ref_id' => (int) $pres->id,
                'observacion_linea' => (string) ($lin->observacion ?? ''),
                'proveedor_id' => (int) ($pres->proveedor_id ?? 0),
                'proveedor_codigo' => $proveedor ? (string) ($proveedor->codigo ?? '') : '',
                'proveedor_nombre' => $proveedor ? (string) ($proveedor->nombre ?? '') : '',
                'condicioncompra_id' => $pres->condicioncompra_id !== null ? (int) $pres->condicioncompra_id : 0,
                'condicionentrega_id' => $pres->condicionentrega_id !== null ? (int) $pres->condicionentrega_id : 0,
                'condicionpago_id' => $pres->condicionpago_id !== null ? (int) $pres->condicionpago_id : 0,
            ];
        }

        return $salida;
    }

    /**
     * @param  array<int, string|null>  $tipos
     * @param  array<int, int|string|null>  $refIds
     */
    public static function presupuestoIdsDistintosUsados(array $tipos, array $refIds): array
    {
        $ids = [];
        $n = count($tipos);
        for ($i = 0; $i < $n; $i++) {
            if (($tipos[$i] ?? '') !== self::ORIGEN_PRESUPUESTO) {
                continue;
            }
            $rid = isset($refIds[$i]) ? (int) $refIds[$i] : 0;
            if ($rid > 0) {
                $ids[$rid] = true;
            }
        }

        return array_keys($ids);
    }
}
