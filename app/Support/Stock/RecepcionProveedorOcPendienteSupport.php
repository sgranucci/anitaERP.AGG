<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\OrdencompraEstados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Órdenes de compra con saldo pendiente de recepción COM (sin COM o COM parcial confirmado).
 */
final class RecepcionProveedorOcPendienteSupport
{
    /**
     * @return array<int, float> ordencompra_articulo_id => cantidad recibida confirmada
     */
    public static function cantidadesRecibidasPorLineaOc(int $ordencompraId): array
    {
        if ($ordencompraId <= 0) {
            return [];
        }

        $filas = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->where('rp.ordencompra_id', $ordencompraId)
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->whereNotNull('rpa.ordencompra_articulo_id')
            ->groupBy('rpa.ordencompra_articulo_id')
            ->selectRaw('rpa.ordencompra_articulo_id as linea_id, SUM(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)) as cantidad_recibida')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->linea_id] = (float) $fila->cantidad_recibida;
        }

        return $out;
    }

    /**
     * @return list<array{
     *   id: int,
     *   numeroordencompra: int,
     *   fecha: ?string,
     *   proveedor_id: int,
     *   proveedor_nombre: string,
     *   empresa_id: int,
     *   empresa_nombre: string,
     *   estadoordencompra: string,
     *   estado_com: string,
     *   cantidad_pedida: float,
     *   cantidad_recibida: float,
     *   cantidad_pendiente: float,
     *   url_consulta: string
     * }>
     */
    public static function buscar(?int $proveedorId, ?string $consulta, int $limite = 80): array
    {
        $limite = max(1, min(200, $limite));
        $consulta = trim((string) $consulta);

        $recibidoSub = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->whereNotNull('rpa.ordencompra_articulo_id')
            ->groupBy('rpa.ordencompra_articulo_id')
            ->selectRaw('rpa.ordencompra_articulo_id as linea_id, SUM(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)) as cantidad_recibida');

        $query = DB::table('ordencompra as oc')
            ->join('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->join('ordencompra_articulo as oa', 'oa.ordencompra_id', '=', 'oc.id')
            ->leftJoinSub($recibidoSub, 'rec', function ($join) {
                $join->on('rec.linea_id', '=', 'oa.id');
            })
            ->where('oc.estadoordencompra', OrdencompraEstados::APROBADA)
            ->when($proveedorId !== null && $proveedorId > 0, function ($q) use ($proveedorId) {
                $q->where('oc.proveedor_id', $proveedorId);
            })
            ->when($consulta !== '', function ($q) use ($consulta) {
                $like = '%'.$consulta.'%';
                $q->where(function ($sub) use ($like, $consulta) {
                    $sub->where('p.nombre', 'like', $like)
                        ->orWhere('oc.detalle', 'like', $like)
                        ->orWhere('oc.comentario', 'like', $like);
                    if (ctype_digit($consulta)) {
                        $sub->orWhere('oc.numeroordencompra', (int) $consulta)
                            ->orWhere('oc.id', (int) $consulta);
                    }
                });
            })
            ->groupBy(
                'oc.id', 'oc.numeroordencompra', 'oc.fecha', 'oc.proveedor_id', 'oc.empresa_id',
                'oc.estadoordencompra', 'p.nombre', 'e.nombre'
            )
            ->havingRaw('SUM(oa.cantidad) > COALESCE(SUM(rec.cantidad_recibida), 0) + 0.000001')
            ->selectRaw('oc.id, oc.numeroordencompra, oc.fecha, oc.proveedor_id, oc.empresa_id, oc.estadoordencompra')
            ->selectRaw('p.nombre as proveedor_nombre, e.nombre as empresa_nombre')
            ->selectRaw('SUM(oa.cantidad) as cantidad_pedida')
            ->selectRaw('COALESCE(SUM(rec.cantidad_recibida), 0) as cantidad_recibida')
            ->orderByDesc('oc.fecha')
            ->orderByDesc('oc.numeroordencompra')
            ->limit($limite);

        RecepcionProveedorVisibilidadSupport::aplicarFiltroOrdencompra($query, 'oc');

        return self::mapearFilas($query->get());
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return list<array<string, mixed>>
     */
    private static function mapearFilas(Collection $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $pedida = (float) ($fila->cantidad_pedida ?? 0);
            $recibida = (float) ($fila->cantidad_recibida ?? 0);
            $pendiente = max(0, $pedida - $recibida);

            $out[] = [
                'id' => (int) $fila->id,
                'numeroordencompra' => (int) $fila->numeroordencompra,
                'fecha' => $fila->fecha ? (string) $fila->fecha : null,
                'proveedor_id' => (int) $fila->proveedor_id,
                'proveedor_nombre' => (string) ($fila->proveedor_nombre ?? ''),
                'empresa_id' => (int) $fila->empresa_id,
                'empresa_nombre' => (string) ($fila->empresa_nombre ?? ''),
                'estadoordencompra' => (string) ($fila->estadoordencompra ?? ''),
                'estado_com' => self::etiquetaEstadoCom($recibida, $pedida),
                'cantidad_pedida' => $pedida,
                'cantidad_recibida' => $recibida,
                'cantidad_pendiente' => $pendiente,
                'url_consulta' => route('editar_ordencompra', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
            ];
        }

        return $out;
    }

    public static function etiquetaEstadoCom(float $recibida, float $pedida): string
    {
        if ($recibida <= 0.000001) {
            return 'SIN COM';
        }

        if ($recibida + 0.000001 < $pedida) {
            return 'COM PARCIAL';
        }

        return 'COM COMPLETA';
    }
}
