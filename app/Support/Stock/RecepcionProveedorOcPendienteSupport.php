<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraLineaEstados;
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
     * Cantidad pendiente de recepcionar en una línea OC activa (respeta tolerancia configurada).
     */
    public static function saldoPendienteLinea(
        float $cantidadPedida,
        float $cantidadRecibida,
        int $empresaId,
        int $centrocostoId
    ): float {
        if ($cantidadPedida <= 0.000001) {
            return 0.0;
        }

        $tol = RecepcionProveedorToleranciaSupport::resolver(
            $empresaId,
            $centrocostoId > 0 ? $centrocostoId : null
        );

        if (RecepcionProveedorToleranciaSupport::cantidadDentroTolerancia($cantidadPedida, $cantidadRecibida, $tol)) {
            return 0.0;
        }

        return max(0.0, $cantidadPedida - $cantidadRecibida);
    }

    public static function lineaTieneSaldoPendiente(
        object $lineaOc,
        float $cantidadRecibida,
        int $empresaId,
        int $centrocostoCabeceraOc
    ): bool {
        if ((string) ($lineaOc->estado_linea_oc ?? OrdencompraLineaEstados::ACTIVA)
            === OrdencompraLineaEstados::CERRADA) {
            return false;
        }

        $ccLinea = (int) ($lineaOc->centrocostodestino_id ?? 0);
        $cc = $ccLinea > 0 ? $ccLinea : $centrocostoCabeceraOc;

        return self::saldoPendienteLinea(
            (float) ($lineaOc->cantidad ?? 0),
            $cantidadRecibida,
            $empresaId,
            $cc
        ) > 0.000001;
    }

    /**
     * @return array{cantidad_pedida: float, cantidad_recibida: float, cantidad_pendiente: float}
     */
    public static function calcularSaldoRecepcion(int $ordencompraId): array
    {
        if ($ordencompraId <= 0) {
            return [
                'cantidad_pedida' => 0.0,
                'cantidad_recibida' => 0.0,
                'cantidad_pendiente' => 0.0,
            ];
        }

        $recibidos = self::cantidadesRecibidasPorLineaOc($ordencompraId);

        $lineas = DB::table('ordencompra_articulo as oa')
            ->where('oa.ordencompra_id', $ordencompraId)
            ->where(function ($q) {
                $q->whereNull('oa.estado_linea_oc')
                    ->orWhere('oa.estado_linea_oc', '!=', OrdencompraLineaEstados::CERRADA);
            })
            ->get(['id', 'cantidad', 'centrocostodestino_id']);

        $oc = DB::table('ordencompra')
            ->where('id', $ordencompraId)
            ->first(['empresa_id', 'centrocosto_id']);

        $empresaId = (int) ($oc->empresa_id ?? 0);
        $ccOc = (int) ($oc->centrocosto_id ?? 0);

        $pedida = 0.0;
        $recibida = 0.0;
        $pendiente = 0.0;
        foreach ($lineas as $linea) {
            $cantPedida = (float) $linea->cantidad;
            $cantRecibida = (float) ($recibidos[(int) $linea->id] ?? 0);
            $ccLinea = (int) ($linea->centrocostodestino_id ?? 0);
            $cc = $ccLinea > 0 ? $ccLinea : $ccOc;
            $saldoLinea = self::saldoPendienteLinea($cantPedida, $cantRecibida, $empresaId, $cc);

            $pedida += $cantPedida;
            $recibida += $cantRecibida;
            $pendiente += $saldoLinea;
        }

        return [
            'cantidad_pedida' => $pedida,
            'cantidad_recibida' => $recibida,
            'cantidad_pendiente' => $pendiente,
        ];
    }

    public static function tieneSaldoPendiente(int $ordencompraId): bool
    {
        $saldo = self::calcularSaldoRecepcion($ordencompraId);

        return $saldo['cantidad_pendiente'] > 0.000001;
    }

    /**
     * Impide alta o precarga de una COM de recepción cuando la OC no admite más cantidades.
     *
     * @throws \RuntimeException
     */
    public static function assertPermiteNuevaRecepcion(Ordencompra $oc): void
    {
        $numero = (int) $oc->numeroordencompra;
        $estado = (string) ($oc->estadoordencompra ?? '');

        if (in_array($estado, [OrdencompraEstados::CUMPLIDA, OrdencompraEstados::CERRADA], true)) {
            throw new \RuntimeException(
                "Orden de compra {$numero} está {$estado}. No puede cargar otra recepción."
            );
        }

        if ($estado === OrdencompraEstados::SUSPENDIDA) {
            throw new \RuntimeException(
                "Orden de compra {$numero} está suspendida. No puede recepcionar hasta reactivarla."
            );
        }

        if (! self::tieneSaldoPendiente((int) $oc->id)) {
            $ultimaCom = DB::table('recepcion_proveedor as rp')
                ->where('rp.ordencompra_id', $oc->id)
                ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
                ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->orderByDesc('rp.id')
                ->value('rp.numerorecepcion');

            $refCom = $ultimaCom ? " (última COM confirmada: {$ultimaCom})" : '';

            throw new \RuntimeException(
                "Orden de compra {$numero} ya fue recepcionada en su totalidad{$refCom}. "
                .'No puede cargar otra recepción sobre la misma OC.'
            );
        }
    }

    /**
     * Valida que las cantidades del remito no superen el saldo pendiente por línea OC.
     *
     * @param  iterable<int, Recepcion_Proveedor_Articulo|object>  $lineasRemito
     *
     * @throws \RuntimeException
     */
    public static function assertRemitoDentroSaldoOc(Ordencompra $oc, iterable $lineasRemito): void
    {
        $recibidos = self::cantidadesRecibidasPorLineaOc((int) $oc->id);
        $oc->loadMissing('ordencompra_articulos.articulos');
        $empresaId = (int) $oc->empresa_id;
        $ccOc = (int) ($oc->centrocosto_id ?? 0);
        $tolEmpresa = RecepcionProveedorToleranciaSupport::resolver($empresaId, $ccOc);

        foreach ($lineasRemito as $linea) {
            $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
            if ($ocArtId <= 0) {
                continue;
            }

            if ((bool) ($linea->fl_cerrar_linea_oc ?? false)) {
                continue;
            }

            $ocArt = $oc->ordencompra_articulos->firstWhere('id', $ocArtId);
            if ($ocArt === null) {
                continue;
            }

            if ((string) ($ocArt->estado_linea_oc ?? OrdencompraLineaEstados::ACTIVA)
                === OrdencompraLineaEstados::CERRADA) {
                continue;
            }

            $enRemito = (float) ($linea->cantidad ?? 0) + (float) ($linea->cantidad_rechazada ?? 0);
            if ($enRemito <= 0.000001) {
                continue;
            }

            $pedida = (float) $ocArt->cantidad;
            $yaRecibida = (float) ($recibidos[$ocArtId] ?? 0);
            $total = $yaRecibida + $enRemito;

            if ($total > $pedida + 0.000001
                && ! RecepcionProveedorToleranciaSupport::cantidadDentroTolerancia($pedida, $total, $tolEmpresa)) {
                $sku = optional($ocArt->articulos)->sku ?? (string) $ocArtId;

                throw new \RuntimeException(
                    "Línea {$sku}: la cantidad del remito ({$enRemito}) más lo ya recepcionado ({$yaRecibida}) "
                    ."supera lo pedido en la OC ({$pedida})."
                );
            }
        }
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
            ->where(function ($q) {
                $q->whereNull('oa.estado_linea_oc')
                    ->orWhere('oa.estado_linea_oc', '!=', \App\Support\Compras\OrdencompraLineaEstados::CERRADA);
            })
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
