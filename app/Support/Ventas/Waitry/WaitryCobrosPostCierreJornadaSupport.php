<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Comandas Waitry colocadas en la ventana de jornada pero cobradas después del cierre operativo.
 * No modifican el Informe Z histórico; sí integran el total que Tesorería debe tomar post-facturación.
 */
final class WaitryCobrosPostCierreJornadaSupport
{
    /**
     * @return array{
     *   tiene_anomalias: bool,
     *   total_cierre_historico: float,
     *   total_post_cierre: float,
     *   total_tesoreria: float,
     *   cantidad_comandas: int,
     *   cierre_jornada_en: ?string,
     *   cierre_jornada_en_fmt: string,
     *   calculado_en: string,
     *   fuente: string,
     *   comandas: list<array<string, mixed>>,
     *   por_medio: list<array<string, mixed>>
     * }
     */
    public static function analizarDesdeCierre(
        JornadaGastronomia $jornada,
        CierreTotemJornadaGastronomia $cierre,
        ?WaitryOrdenesExternasService $ordenesExternasService = null,
    ): array {
        $cierreEn = $jornada->cierre_en;
        if ($cierreEn === null) {
            return self::vacio();
        }

        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $totalCierre = round((float) ($detalle['resumen_informe_z']['total_general']['total_ingreso'] ?? 0), 2);
        $empresaId = (int) $jornada->empresa_id;
        $jornadaId = (int) $jornada->id;

        $lineas = self::lineasParaAnalisis($jornadaId);
        if ($lineas === []) {
            return self::resultado($totalCierre, [], $cierreEn, 'sin_datos');
        }

        $totalProceso = self::totalInformeZDesdeLineas($lineas, $empresaId);
        if (abs($totalProceso - $totalCierre) <= 0.01) {
            return self::resultado($totalCierre, [], $cierreEn, 'sin_diferencia_proceso');
        }

        $aperturaEn = $jornada->apertura_en;
        $idsPrioritarios = self::ultimosOrderIdsEnLineas($lineas, 30);
        $comandas = [];
        $requierenCobroEn = [];

        foreach ($lineas as $ln) {
            if (! is_array($ln) || ! WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln)) {
                continue;
            }

            $orderId = (int) ($ln['waitry_order_id'] ?? 0);
            if ($orderId <= 0 || ! isset($idsPrioritarios[$orderId])) {
                continue;
            }

            $placedAt = self::parseInstante($ln['placed_at'] ?? null);
            if ($placedAt === null) {
                continue;
            }

            if ($aperturaEn !== null && $placedAt->lt($aperturaEn)) {
                continue;
            }

            if ($placedAt->gt($cierreEn)) {
                continue;
            }

            if (! WaitryTotemJornadaResumenSupport::cobradaEnWaitryLinea($ln)) {
                continue;
            }

            $cobroEn = WaitryOrdenCobroSupport::instanteCobroWaitry($ln);
            if ($cobroEn === null) {
                $requierenCobroEn[$orderId] = $ln;

                continue;
            }

            if (! $cobroEn->gt($cierreEn)) {
                continue;
            }

            $comandas[] = self::armarFilaComanda($ln, $placedAt, $cobroEn, $cierreEn, $empresaId);
        }

        if ($requierenCobroEn !== [] && $ordenesExternasService !== null) {
            krsort($requierenCobroEn, SORT_NUMERIC);
            $limite = min(count($requierenCobroEn), 30);
            $consultadas = 0;
            foreach ($requierenCobroEn as $orderId => $ln) {
                if ($limite > 0 && $consultadas >= $limite) {
                    break;
                }
                $orden = $ordenesExternasService->obtenerOrdenPorIdConciliacion($empresaId, $orderId);
                $consultadas++;
                if ($orden === null) {
                    continue;
                }
                $cobroEn = WaitryOrdenCobroSupport::instanteCobroWaitry($orden);
                if ($cobroEn === null || ! $cobroEn->gt($cierreEn)) {
                    continue;
                }
                $placedAt = self::parseInstante($ln['placed_at'] ?? null)
                    ?? WaitryOrdenCobroSupport::instanteCobroWaitry(['placed_at' => $orden['placed_at'] ?? $orden['placedAt'] ?? null]);
                if ($placedAt === null || $placedAt->gt($cierreEn)) {
                    continue;
                }
                $comandas[] = self::armarFilaComanda($ln, $placedAt, $cobroEn, $cierreEn, $empresaId);
            }
        }

        usort($comandas, static function (array $a, array $b): int {
            return strcmp((string) ($a['cobro_en'] ?? ''), (string) ($b['cobro_en'] ?? ''));
        });

        self::enriquecerFacturacionProceso($comandas, $empresaId, $jornada);

        return self::resultado($totalCierre, $comandas, $cierreEn, 'proceso_snapshot');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<int, true>
     */
    private static function ultimosOrderIdsEnLineas(array $lineas, int $cantidad): array
    {
        $ids = [];
        foreach ($lineas as $ln) {
            if (! is_array($ln)) {
                continue;
            }
            $id = (int) ($ln['waitry_order_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        rsort($ids, SORT_NUMERIC);
        $slice = array_slice($ids, 0, max(1, $cantidad));

        return array_fill_keys($slice, true);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function totalInformeZDesdeLineas(array $lineas, int $empresaId): float
    {
        $total = 0.0;
        foreach ($lineas as $ln) {
            if (! is_array($ln) || ! WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln)) {
                continue;
            }
            $monto = round((float) ($ln['monto_cobro_waitry'] ?? $ln['total'] ?? $ln['monto'] ?? 0), 2);
            if ($monto <= 0.0001) {
                $monto = round((float) ($ln['total_amount_waitry'] ?? 0), 2);
            }
            $total += $monto;
        }

        return round($total, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lineasParaAnalisis(int $jornadaId): array
    {
        if ($jornadaId <= 0) {
            return [];
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($snapshot === null) {
            return [];
        }

        $lineas = [];
        foreach ($snapshot->lineas() as $ln) {
            if (! is_array($ln)) {
                continue;
            }
            if (! empty($ln['discrepancia_gap'])) {
                continue;
            }
            if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($ln)) {
                continue;
            }
            $lineas[] = $ln;
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $ln
     * @return array<string, mixed>
     */
    private static function armarFilaComanda(
        array $ln,
        CarbonInterface $placedAt,
        CarbonInterface $cobroEn,
        CarbonInterface $cierreEn,
        int $empresaId,
    ): array {
        $monto = round((float) ($ln['monto_cobro_waitry'] ?? $ln['total'] ?? $ln['monto'] ?? 0), 2);
        if ($monto <= 0.0001) {
            $monto = round((float) ($ln['total_amount_waitry'] ?? 0), 2);
        }

        $tipoInformeZ = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId);
        $categoria = WaitryMedioPagoCuentacajaSupport::categoriaInformeZDesglose(
            $tipoInformeZ ?? ($ln['waitry_tipo_pago'] ?? null),
            $ln['waitry_payment_gateway'] ?? null,
        ) ?? $tipoInformeZ ?? 'totem';

        return [
            'waitry_order_id' => (int) ($ln['waitry_order_id'] ?? 0),
            'display_id' => (string) ($ln['display_id'] ?? ''),
            'total' => $monto,
            'medio_clave' => (string) $categoria,
            'medio_etiqueta' => WaitryMedioPagoCuentacajaSupport::etiquetaCategoriaInformeZ((string) $categoria),
            'waitry_layout_name' => (string) ($ln['waitry_layout_name'] ?? ''),
            'placed_at' => $placedAt->format('Y-m-d H:i:s'),
            'placed_at_fmt' => $placedAt->format('d/m/Y H:i'),
            'cobro_en' => $cobroEn->format('Y-m-d H:i:s'),
            'cobro_en_fmt' => $cobroEn->format('d/m/Y H:i'),
            'minutos_despues_cierre' => max(0, (int) $cierreEn->diffInMinutes($cobroEn, false)),
            'facturada_proceso' => false,
            'numero_comprobante' => null,
            'cierre_jornada_proceso_lote' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $comandas
     */
    private static function enriquecerFacturacionProceso(array &$comandas, int $empresaId, JornadaGastronomia $jornada): void
    {
        if ($comandas === []) {
            return;
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d');
        if ($fechaJornada === null) {
            return;
        }

        $orderIds = array_values(array_filter(array_map(
            static fn (array $c) => (int) ($c['waitry_order_id'] ?? 0),
            $comandas,
        )));

        if ($orderIds === []) {
            return;
        }

        $emis = DB::table('venta_gastronomia_emision as vge')
            ->join('venta as v', 'v.id', '=', 'vge.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('pv.empresa_id', $empresaId)
            ->where('vge.identificador_pc', 'CIERRE-JORNADA-WAITRY')
            ->where(function ($q) use ($fechaJornada) {
                $q->whereDate('v.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('v.fechajornada')
                            ->whereDate('v.fecha', $fechaJornada);
                    });
            })
            ->get([
                'vge.waitry_order_id',
                'vge.waitry_comandas_json',
                'vge.cierre_jornada_proceso_lote',
                'v.numerocomprobante',
                'pv.codigo as pv_codigo',
            ]);

        $mapa = [];
        foreach ($emis as $e) {
            $oidDirecto = (int) ($e->waitry_order_id ?? 0);
            if ($oidDirecto > 0) {
                $mapa[$oidDirecto] = $e;
            }
            $json = json_decode($e->waitry_comandas_json ?? '[]', true);
            if (! is_array($json)) {
                continue;
            }
            foreach ($json as $item) {
                $oid = is_array($item)
                    ? (int) ($item['waitry_order_id'] ?? $item['order_id'] ?? 0)
                    : (int) $item;
                if ($oid > 0) {
                    $mapa[$oid] = $e;
                }
            }
        }

        foreach ($comandas as &$comanda) {
            $oid = (int) ($comanda['waitry_order_id'] ?? 0);
            if ($oid <= 0 || ! isset($mapa[$oid])) {
                continue;
            }
            $e = $mapa[$oid];
            $comanda['facturada_proceso'] = true;
            $pvCodigo = (string) ($e->pv_codigo ?? '');
            $nro = (string) ($e->numerocomprobante ?? '');
            $comanda['numero_comprobante'] = $pvCodigo !== '' && $nro !== ''
                ? 'FAC B-'.$pvCodigo.'-'.$nro
                : $nro;
            $comanda['cierre_jornada_proceso_lote'] = isset($e->cierre_jornada_proceso_lote)
                ? (int) $e->cierre_jornada_proceso_lote
                : null;
        }
        unset($comanda);
    }

    /**
     * @param  list<array<string, mixed>>  $comandas
     * @return array<string, mixed>
     */
    private static function resultado(
        float $totalCierre,
        array $comandas,
        CarbonInterface $cierreEn,
        string $fuente,
    ): array {
        $porMedio = [];
        $totalPost = 0.0;

        foreach ($comandas as $comanda) {
            $monto = round((float) ($comanda['total'] ?? 0), 2);
            $totalPost += $monto;
            $clave = (string) ($comanda['medio_clave'] ?? 'totem');
            if (! isset($porMedio[$clave])) {
                $porMedio[$clave] = [
                    'medio_clave' => $clave,
                    'medio_etiqueta' => (string) ($comanda['medio_etiqueta'] ?? $clave),
                    'total' => 0.0,
                    'cantidad' => 0,
                ];
            }
            $porMedio[$clave]['total'] = round($porMedio[$clave]['total'] + $monto, 2);
            $porMedio[$clave]['cantidad']++;
        }

        $totalPost = round($totalPost, 2);

        return [
            'tiene_anomalias' => $comandas !== [],
            'total_cierre_historico' => round($totalCierre, 2),
            'total_post_cierre' => $totalPost,
            'total_tesoreria' => round($totalCierre + $totalPost, 2),
            'cantidad_comandas' => count($comandas),
            'cierre_jornada_en' => $cierreEn->format('Y-m-d H:i:s'),
            'cierre_jornada_en_fmt' => $cierreEn->format('d/m/Y H:i'),
            'calculado_en' => now()->format('Y-m-d H:i:s'),
            'fuente' => $fuente,
            'comandas' => $comandas,
            'por_medio' => array_values($porMedio),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function vacio(): array
    {
        return [
            'tiene_anomalias' => false,
            'total_cierre_historico' => 0.0,
            'total_post_cierre' => 0.0,
            'total_tesoreria' => 0.0,
            'cantidad_comandas' => 0,
            'cierre_jornada_en' => null,
            'cierre_jornada_en_fmt' => '',
            'calculado_en' => now()->format('Y-m-d H:i:s'),
            'fuente' => 'sin_jornada_cerrada',
            'comandas' => [],
            'por_medio' => [],
        ];
    }

    private static function parseInstante(mixed $valor): ?CarbonInterface
    {
        if ($valor instanceof CarbonInterface) {
            return $valor;
        }

        if (is_string($valor) && trim($valor) !== '') {
            try {
                return Carbon::parse($valor);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
