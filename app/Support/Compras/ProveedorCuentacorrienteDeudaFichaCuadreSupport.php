<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Configuracion\CotizacionVigenteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Compara, al corte, la deuda abierta de proveedores contra la ficha de cuenta corriente.
 * Las dos lecturas son las del reporte Deuda / ficha: mismas filas, mismas aplicaciones
 * y la cotización grabada en el comprobante.
 *
 * Pesos: cada movimiento × su cotización histórica.
 * Dólares: esos pesos ÷ el dólar vigente de la fecha de ese movimiento.
 *
 * No consulta Anita ni el bridge. El renglón de ajuste no lleva factura ni orden de pago,
 * así que entra en la ficha y no en la deuda.
 */
final class ProveedorCuentacorrienteDeudaFichaCuadreSupport
{
    public const FECHA_CORTE_DEFAULT = '2026-09-30';

    /**
     * @return array{
     *   fecha: string,
     *   leyenda: string,
     *   moneda_dolar_id: int,
     *   cotizacion_dolar_corte: float,
     *   cotizacion_dolar_fecha: string|null,
     *   tolerancia: float,
     *   resumen: array<string, int|float>,
     *   grupos: list<array<string, mixed>>
     * }
     */
    public function cuadrar(
        string $fechaCorte,
        ?int $empresaId = null,
        ?int $proveedorId = null,
        float $tolerancia = ProveedorCuentacorrienteConciliacionSupport::TOLERANCIA,
    ): array {
        $fechaCorte = $this->fechaYmd($fechaCorte);
        $leyenda = ProveedorCuentacorrienteGrillaSupport::PREFIJO_LEYENDA_AJUSTE
            .date('d/m/Y', strtotime($fechaCorte));
        $monedaDolar = $this->resolverMonedaDolar();
        $cotDolarCorte = CotizacionVigenteSupport::venta($fechaCorte, $monedaDolar['id']);
        $cotCorte = (float) $cotDolarCorte['valor'];
        if ($cotCorte <= 0) {
            throw new \RuntimeException(
                'No hay cotización de dólar vigente al '.$fechaCorte.'. Sin eso no se puede armar el control en dólares ni el efecto del ajuste.'
            );
        }

        $grupos = [];
        $resumen = [
            'movimientos' => 0,
            'grupos' => 0,
            'con_diferencia_pesos' => 0,
            'con_diferencia_dolares' => 0,
            'ajustes_a_grabar' => 0,
            'ajustes_a_borrar' => 0,
            'filas_cotizacion_dia' => 0,
            'filas_sin_cotizacion' => 0,
            'filas_sin_dolar' => 0,
            'suma_diferencia_pesos' => 0.0,
            'suma_diferencia_dolares' => 0.0,
            'suma_ajuste_propuesto' => 0.0,
            'suma_dolares_despues' => 0.0,
        ];

        $query = DB::table('proveedor_cuentacorriente as cc')
            ->join('proveedor as p', function ($join) {
                $join->on('p.id', '=', 'cc.proveedor_id')->whereNull('p.deleted_at');
            })
            ->join('empresa as e', 'e.id', '=', 'cc.empresa_id')
            ->leftJoin('pagoproveedor as pp', 'pp.id', '=', 'cc.pagoproveedor_id')
            ->leftJoinSub(
                DB::table('proveedor_cuentacorriente_aplicacion')
                    ->select('proveedor_cuentacorriente_id')
                    ->selectRaw('SUM(total) as aplicado')
                    ->groupBy('proveedor_cuentacorriente_id'),
                'apl',
                'apl.proveedor_cuentacorriente_id',
                '=',
                'cc.id'
            )
            ->whereDate('cc.fecha', '<=', $fechaCorte)
            ->select([
                'cc.id',
                'cc.fecha',
                'cc.proveedor_id',
                'cc.empresa_id',
                'cc.total',
                'cc.moneda_id',
                'cc.cotizacion',
                'cc.comprobante_proveedor_id',
                'cc.pagoproveedor_id',
                'cc.leyenda',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'e.nombre as empresa_nombre',
                'pp.tipocomprobante',
                'apl.aplicado',
            ])
            ->orderBy('cc.id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('cc.empresa_id', $empresaId);
        }
        if ($proveedorId !== null && $proveedorId > 0) {
            $query->where('cc.proveedor_id', $proveedorId);
        }

        $query->chunkById(800, function ($filas) use (&$grupos, &$resumen, $leyenda, $monedaDolar, $fechaCorte) {
            foreach ($filas as $fila) {
                $resumen['movimientos']++;
                $key = ((int) $fila->proveedor_id).'|'.((int) $fila->empresa_id);
                if (! isset($grupos[$key])) {
                    $grupos[$key] = $this->grupoVacio($fila);
                }

                $esAjuste = trim((string) ($fila->leyenda ?? '')) === $leyenda
                    && (int) ($fila->comprobante_proveedor_id ?? 0) === 0
                    && (int) ($fila->pagoproveedor_id ?? 0) === 0;

                $pesosTotal = $this->aPesos($fila, $resumen, null, true);
                $usdTotal = $this->aDolares($pesosTotal, $this->ymd($fila->fecha), $monedaDolar['id'], $resumen, true);

                $grupos[$key]['ficha_pesos'] += $pesosTotal;
                if ($usdTotal !== null) {
                    $grupos[$key]['ficha_usd'] += $usdTotal;
                } else {
                    $grupos[$key]['usd_incompleto'] = true;
                }

                if ($esAjuste) {
                    $grupos[$key]['ajuste_ids'][] = (int) $fila->id;
                    $grupos[$key]['ajuste_actual'] += (float) $fila->total;

                    continue;
                }

                $grupos[$key]['ficha_pesos_sin_ajuste'] += $pesosTotal;
                if ($usdTotal !== null) {
                    $grupos[$key]['ficha_usd_sin_ajuste'] += $usdTotal;
                }

                if (! $this->esDeudaAbierta($fila)) {
                    continue;
                }

                $pendiente = ProveedorCuentacorrienteGrillaSupport::saldoPendiente(
                    (float) $fila->total,
                    (float) ($fila->aplicado ?? 0)
                );
                $pesosPendiente = $this->aPesos($fila, $resumen, $pendiente, false);
                $usdPendiente = $this->aDolares($pesosPendiente, $this->ymd($fila->fecha), $monedaDolar['id'], $resumen, false);
                $grupos[$key]['deuda_pesos'] += $pesosPendiente;
                if ($usdPendiente !== null) {
                    $grupos[$key]['deuda_usd'] += $usdPendiente;
                } else {
                    $grupos[$key]['usd_incompleto'] = true;
                }
            }
        }, 'cc.id', 'id');

        $salida = [];
        foreach ($grupos as $grupo) {
            $armado = $this->cerrarGrupo($grupo, $cotCorte, $tolerancia);
            if ($armado === null) {
                continue;
            }
            $salida[] = $armado;
            $resumen['con_diferencia_pesos'] += abs($armado['diferencia_pesos']) >= $tolerancia ? 1 : 0;
            $resumen['con_diferencia_dolares'] += abs($armado['diferencia_usd']) >= $tolerancia ? 1 : 0;
            $resumen['suma_diferencia_pesos'] += $armado['diferencia_pesos'];
            $resumen['suma_diferencia_dolares'] += $armado['diferencia_usd'];
            $resumen['suma_dolares_despues'] += $armado['diferencia_usd_despues'];
            if ($armado['accion'] === 'grabar') {
                $resumen['ajustes_a_grabar']++;
                $resumen['suma_ajuste_propuesto'] += $armado['ajuste_propuesto'];
            } elseif ($armado['accion'] === 'borrar') {
                $resumen['ajustes_a_borrar']++;
            }
        }

        usort($salida, function (array $a, array $b): int {
            return abs($b['diferencia_pesos']) <=> abs($a['diferencia_pesos']);
        });

        $resumen['grupos'] = count($salida);
        foreach ([
            'suma_diferencia_pesos',
            'suma_diferencia_dolares',
            'suma_ajuste_propuesto',
            'suma_dolares_despues',
        ] as $clave) {
            $resumen[$clave] = round((float) $resumen[$clave], 2);
        }

        return [
            'fecha' => $fechaCorte,
            'leyenda' => $leyenda,
            'moneda_dolar_id' => $monedaDolar['id'],
            'moneda_dolar_abreviatura' => $monedaDolar['abreviatura'],
            'cotizacion_dolar_corte' => $cotCorte,
            'cotizacion_dolar_fecha' => $cotDolarCorte['fecha'],
            'tolerancia' => $tolerancia,
            'resumen' => $resumen,
            'grupos' => $salida,
        ];
    }

    /**
     * @param  object  $fila
     * @return array<string, mixed>
     */
    private function grupoVacio(object $fila): array
    {
        return [
            'proveedor_id' => (int) $fila->proveedor_id,
            'proveedor_codigo' => trim((string) ($fila->proveedor_codigo ?? '')),
            'proveedor_nombre' => trim((string) ($fila->proveedor_nombre ?? '')),
            'empresa_id' => (int) $fila->empresa_id,
            'empresa_nombre' => trim((string) ($fila->empresa_nombre ?? '')),
            'deuda_pesos' => 0.0,
            'ficha_pesos' => 0.0,
            'ficha_pesos_sin_ajuste' => 0.0,
            'deuda_usd' => 0.0,
            'ficha_usd' => 0.0,
            'ficha_usd_sin_ajuste' => 0.0,
            'ajuste_actual' => 0.0,
            'ajuste_ids' => [],
            'usd_incompleto' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $grupo
     * @return array<string, mixed>|null
     */
    private function cerrarGrupo(array $grupo, float $cotDolarCorte, float $tolerancia): ?array
    {
        $deudaPesos = round((float) $grupo['deuda_pesos'], 2);
        $fichaPesos = round((float) $grupo['ficha_pesos'], 2);
        $deudaUsd = round((float) $grupo['deuda_usd'], 2);
        $fichaUsd = round((float) $grupo['ficha_usd'], 2);
        $ajusteActual = round((float) $grupo['ajuste_actual'], 2);
        $ajustePropuesto = round((float) $grupo['deuda_pesos'] - (float) $grupo['ficha_pesos_sin_ajuste'], 2);
        if (abs($ajustePropuesto) < $tolerancia) {
            $ajustePropuesto = 0.0;
        }

        $diferenciaPesos = round($deudaPesos - $fichaPesos, 2);
        $diferenciaUsd = round($deudaUsd - $fichaUsd, 2);
        $usdAjuste = round($ajustePropuesto / $cotDolarCorte, 2);
        $fichaUsdDespues = round((float) $grupo['ficha_usd_sin_ajuste'] + $usdAjuste, 2);
        $diferenciaUsdDespues = round($deudaUsd - $fichaUsdDespues, 2);

        $cambiaAjuste = abs($ajustePropuesto - $ajusteActual) >= $tolerancia
            || ($ajustePropuesto == 0.0 && $grupo['ajuste_ids'] !== []);
        $hayDiferencia = abs($diferenciaPesos) >= $tolerancia || abs($diferenciaUsd) >= $tolerancia;
        if (! $hayDiferencia && ! $cambiaAjuste) {
            return null;
        }

        $accion = 'ninguna';
        if ($ajustePropuesto == 0.0 && $grupo['ajuste_ids'] !== []) {
            $accion = 'borrar';
        } elseif (abs($ajustePropuesto - $ajusteActual) >= $tolerancia) {
            $accion = 'grabar';
        }

        return [
            'proveedor_id' => $grupo['proveedor_id'],
            'proveedor_codigo' => $grupo['proveedor_codigo'],
            'proveedor_nombre' => $grupo['proveedor_nombre'],
            'empresa_id' => $grupo['empresa_id'],
            'empresa_nombre' => $grupo['empresa_nombre'],
            'deuda_pesos' => $deudaPesos,
            'ficha_pesos' => $fichaPesos,
            'diferencia_pesos' => $diferenciaPesos,
            'deuda_usd' => $deudaUsd,
            'ficha_usd' => $fichaUsd,
            'diferencia_usd' => $diferenciaUsd,
            'ajuste_actual' => $ajusteActual,
            'ajuste_propuesto' => $ajustePropuesto,
            'ajuste_ids' => $grupo['ajuste_ids'],
            'diferencia_usd_despues' => $diferenciaUsdDespues,
            'usd_incompleto' => (bool) $grupo['usd_incompleto'],
            'accion' => $accion,
        ];
    }

    /**
     * Misma regla que el reporte: cotización del comprobante; si no tiene, la vigente del día.
     *
     * @param  array<string, int|float>  $resumen
     */
    private function aPesos(object $fila, array &$resumen, ?float $importe = null, bool $contar = false): float
    {
        $monto = $importe ?? (float) $fila->total;
        $monedaId = (int) ($fila->moneda_id ?? CotizacionVigenteSupport::MONEDA_LOCAL_ID);
        if ($monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return $monto;
        }

        $cotDoc = (float) ($fila->cotizacion ?? 0);
        $cotizacion = $cotDoc;
        if ($cotDoc <= 0) {
            $cotizacion = CotizacionVigenteSupport::ventaValor($this->ymd($fila->fecha), $monedaId);
            if ($cotizacion > 0) {
                if ($contar) {
                    $resumen['filas_cotizacion_dia']++;
                }
            } else {
                $cotizacion = 1.0;
                if ($contar) {
                    $resumen['filas_sin_cotizacion']++;
                }
            }
        }

        $coef = (float) calculaCoeficienteMoneda(
            CotizacionVigenteSupport::MONEDA_LOCAL_ID,
            $monedaId,
            $cotizacion
        );

        return round($monto * $coef, 2);
    }

    /**
     * @param  array<string, int|float>  $resumen
     */
    private function aDolares(float $pesos, string $fecha, int $monedaDolarId, array &$resumen, bool $contar = false): ?float
    {
        $cotizacion = CotizacionVigenteSupport::ventaValor($fecha, $monedaDolarId);
        if ($cotizacion <= 0) {
            if ($contar) {
                $resumen['filas_sin_dolar']++;
            }

            return null;
        }

        return round($pesos / $cotizacion, 2);
    }

    private function esDeudaAbierta(object $fila): bool
    {
        $total = (float) $fila->total;
        $aplicado = (float) ($fila->aplicado ?? 0);
        if (abs($aplicado) >= abs($total)) {
            return false;
        }

        $comprobanteId = (int) ($fila->comprobante_proveedor_id ?? 0);
        $pagoId = (int) ($fila->pagoproveedor_id ?? 0);
        if ($comprobanteId > 0 && $pagoId === 0) {
            return true;
        }

        return $pagoId > 0
            && $total < 0
            && strtoupper(trim((string) ($fila->tipocomprobante ?? ''))) === 'OPA';
    }

    /**
     * @return array{id: int, abreviatura: string}
     */
    private function resolverMonedaDolar(): array
    {
        $candidatas = ['USD', 'U$S', 'U$D', 'US$', 'DOL', 'DOLAR'];
        $monedas = DB::table('moneda')->get(['id', 'abreviatura']);
        foreach ($monedas as $moneda) {
            $abrev = strtoupper(trim((string) $moneda->abreviatura));
            if (in_array($abrev, $candidatas, true)) {
                return ['id' => (int) $moneda->id, 'abreviatura' => $abrev];
            }
        }

        throw new \RuntimeException('No hay una moneda dólar (USD / U$S) en la tabla moneda.');
    }

    private function fechaYmd(string $fecha): string
    {
        $ts = strtotime($fecha);
        if ($ts === false) {
            throw new \RuntimeException('Fecha de corte inválida: '.$fecha);
        }

        return date('Y-m-d', $ts);
    }

    private function ymd(mixed $fecha): string
    {
        $texto = substr(trim((string) $fecha), 0, 10);
        $ts = strtotime($texto);

        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
