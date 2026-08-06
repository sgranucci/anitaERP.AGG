<?php

namespace App\Support\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Armado de buckets para el aviso diario de OC abiertas.
 */
final class OrdencompraAlertasAbiertasSupport
{
    /**
     * @return array{
     *   dias_sin_recepcion: int,
     *   fecha: string,
     *   sin_recepcion: list<array<string, mixed>>,
     *   parciales: list<array<string, mixed>>,
     *   vencidas: list<array<string, mixed>>,
     *   saldos_pendientes: list<array<string, mixed>>,
     *   total_sin_recepcion: int,
     *   total_parciales: int,
     *   total_vencidas: int,
     *   total_saldos_pendientes: int,
     * }
     */
    public static function recopilar(?int $empresaId = null, ?int $diasSinRecepcion = null, ?int $limitePorSeccion = null): array
    {
        $dias = max(1, (int) ($diasSinRecepcion ?? config('compras.oc_alertas_abiertas.dias_sin_recepcion', 7)));
        $limite = max(1, min(
            500,
            (int) ($limitePorSeccion ?? config('compras.oc_alertas_abiertas.limite_por_seccion', 80))
        ));
        $hoy = Carbon::today();
        $fechaLimiteSinRecepcion = $hoy->copy()->subDays($dias)->toDateString();

        $filas = self::consultarAbiertasConSaldo($empresaId);

        $sinRecepcion = [];
        $parciales = [];
        $vencidas = [];
        $saldos = [];

        foreach ($filas as $fila) {
            $pedida = (float) ($fila->cantidad_pedida ?? 0);
            $recibida = max(0.0, (float) ($fila->cantidad_recibida ?? 0));
            $pendiente = max(0.0, $pedida - $recibida);
            if ($pendiente <= 0.000001) {
                continue;
            }

            $item = self::mapearFila($fila, $pedida, $recibida, $pendiente);
            $saldos[] = $item;

            $estadoCom = RecepcionProveedorOcPendienteSupport::etiquetaEstadoCom($recibida, $pedida);
            if ($estadoCom === 'SIN COM'
                && ! empty($fila->fecha)
                && (string) $fila->fecha <= $fechaLimiteSinRecepcion
            ) {
                $sinRecepcion[] = $item;
            }

            if ($estadoCom === 'COM PARCIAL') {
                $parciales[] = $item;
            }

            if (! empty($fila->fechaentrega)
                && (string) $fila->fechaentrega < $hoy->toDateString()
            ) {
                $vencidas[] = $item;
            }
        }

        return [
            'dias_sin_recepcion' => $dias,
            'fecha' => $hoy->format('d/m/Y'),
            'sin_recepcion' => array_slice($sinRecepcion, 0, $limite),
            'parciales' => array_slice($parciales, 0, $limite),
            'vencidas' => array_slice($vencidas, 0, $limite),
            'saldos_pendientes' => array_slice($saldos, 0, $limite),
            'total_sin_recepcion' => count($sinRecepcion),
            'total_parciales' => count($parciales),
            'total_vencidas' => count($vencidas),
            'total_saldos_pendientes' => count($saldos),
        ];
    }

    public static function hayAlertas(array $alertas): bool
    {
        return ((int) ($alertas['total_sin_recepcion'] ?? 0))
            + ((int) ($alertas['total_parciales'] ?? 0))
            + ((int) ($alertas['total_vencidas'] ?? 0))
            + ((int) ($alertas['total_saldos_pendientes'] ?? 0)) > 0;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  int  $totalReal
     */
    public static function formatearLista(array $items, int $totalReal): string
    {
        if ($items === []) {
            return '(ninguna)';
        }

        $lineas = [];
        foreach ($items as $item) {
            $lineas[] = sprintf(
                'OC %s | %s | %s | Fecha %s | Entrega %s | Pedido %s | Recibido %s | Pendiente %s | %s',
                $item['numero'],
                $item['empresa'],
                $item['proveedor'],
                $item['fecha'],
                $item['fecha_entrega'],
                self::fmtCant($item['cantidad_pedida']),
                self::fmtCant($item['cantidad_recibida']),
                self::fmtCant($item['cantidad_pendiente']),
                $item['estado_com']
            );
        }

        $texto = implode("\n", $lineas);
        $mostrados = count($items);
        if ($totalReal > $mostrados) {
            $texto .= "\n… y ".($totalReal - $mostrados).' más';
        }

        return $texto;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function consultarAbiertasConSaldo(?int $empresaId)
    {
        $recibidoSub = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereIn('rp.tipo', [
                Recepcion_Proveedor::TIPO_RECEPCION,
                Recepcion_Proveedor::TIPO_DEVOLUCION,
            ])
            ->whereNotNull('rpa.ordencompra_articulo_id')
            ->groupBy('rpa.ordencompra_articulo_id')
            ->selectRaw(
                'rpa.ordencompra_articulo_id as linea_id, SUM('
                .'CASE rp.tipo '
                .'WHEN ? THEN rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0) '
                .'WHEN ? THEN -(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)) '
                .'ELSE 0 END) as cantidad_recibida',
                [
                    Recepcion_Proveedor::TIPO_RECEPCION,
                    Recepcion_Proveedor::TIPO_DEVOLUCION,
                ]
            );

        $query = DB::table('ordencompra as oc')
            ->join('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->join('ordencompra_articulo as oa', 'oa.ordencompra_id', '=', 'oc.id')
            ->where(function ($q) {
                $q->whereNull('oa.estado_linea_oc')
                    ->orWhere('oa.estado_linea_oc', '!=', OrdencompraLineaEstados::CERRADA);
            })
            ->leftJoinSub($recibidoSub, 'rec', function ($join) {
                $join->on('rec.linea_id', '=', 'oa.id');
            })
            ->whereIn('oc.estadoordencompra', [
                OrdencompraEstados::APROBADA,
                OrdencompraEstados::CUMPLIDA,
            ])
            ->when($empresaId !== null && $empresaId > 0, function ($q) use ($empresaId) {
                $q->where('oc.empresa_id', $empresaId);
            })
            ->groupBy(
                'oc.id',
                'oc.numeroordencompra',
                'oc.fecha',
                'oc.fechaentrega',
                'oc.proveedor_id',
                'oc.empresa_id',
                'oc.estadoordencompra',
                'p.nombre',
                'e.nombre'
            )
            ->havingRaw('SUM(oa.cantidad) > COALESCE(SUM(rec.cantidad_recibida), 0) + 0.000001')
            ->selectRaw('oc.id, oc.numeroordencompra, oc.fecha, oc.fechaentrega, oc.proveedor_id, oc.empresa_id, oc.estadoordencompra')
            ->selectRaw('p.nombre as proveedor_nombre, e.nombre as empresa_nombre')
            ->selectRaw('SUM(oa.cantidad) as cantidad_pedida')
            ->selectRaw('COALESCE(SUM(rec.cantidad_recibida), 0) as cantidad_recibida')
            ->orderBy('oc.fechaentrega')
            ->orderBy('oc.fecha')
            ->orderBy('oc.numeroordencompra');

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapearFila(object $fila, float $pedida, float $recibida, float $pendiente): array
    {
        return [
            'id' => (int) $fila->id,
            'numero' => (string) ((int) $fila->numeroordencompra),
            'empresa_id' => (int) $fila->empresa_id,
            'empresa' => (string) ($fila->empresa_nombre ?? ''),
            'proveedor' => (string) ($fila->proveedor_nombre ?? ''),
            'fecha' => self::fmtFecha($fila->fecha ?? null),
            'fecha_entrega' => self::fmtFecha($fila->fechaentrega ?? null),
            'estado' => (string) ($fila->estadoordencompra ?? ''),
            'estado_com' => RecepcionProveedorOcPendienteSupport::etiquetaEstadoCom($recibida, $pedida),
            'cantidad_pedida' => $pedida,
            'cantidad_recibida' => $recibida,
            'cantidad_pendiente' => $pendiente,
        ];
    }

    private static function fmtFecha(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }

        try {
            return Carbon::parse((string) $fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private static function fmtCant(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, ',', '.'), '0'), ',');
    }
}
