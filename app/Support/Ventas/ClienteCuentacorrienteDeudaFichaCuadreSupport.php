<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Compras\ProveedorCuentacorrienteConciliacionSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Compara, al corte, la deuda abierta de clientes contra la ficha de cuenta corriente.
 * Mismas reglas de alcance que el reporte Deuda / ficha
 * ({@see ClienteCuentacorrienteDeudaAlcanceSupport}).
 *
 * Expresión origen: saldos en la moneda de cada movimiento.
 * Expresión pesos: cada movimiento × cotización histórica; dólares = pesos ÷ dólar
 * vigente de la fecha del movimiento.
 *
 * El renglón de ajuste no lleva venta ni cobranza: entra en la ficha y no en la deuda.
 */
final class ClienteCuentacorrienteDeudaFichaCuadreSupport
{
    public const FECHA_CORTE_DEFAULT = '2026-10-18';

    public const EXPRESION_ORIGEN = 'origen';

    public const EXPRESION_PESOS = 'pesos';

    /**
     * @return array{
     *   fecha: string,
     *   expresion: string,
     *   leyenda: string,
     *   moneda_dolar_id: int|null,
     *   cotizacion_dolar_corte: float|null,
     *   cotizacion_dolar_fecha: string|null,
     *   tolerancia: float,
     *   resumen: array<string, int|float>,
     *   grupos: list<array<string, mixed>>
     * }
     */
    public function cuadrar(
        string $fechaCorte,
        ?int $empresaId = null,
        ?int $clienteId = null,
        float $tolerancia = ProveedorCuentacorrienteConciliacionSupport::TOLERANCIA,
        string $expresion = self::EXPRESION_ORIGEN,
    ): array {
        $fechaCorte = $this->fechaYmd($fechaCorte);
        $expresion = $expresion === self::EXPRESION_PESOS
            ? self::EXPRESION_PESOS
            : self::EXPRESION_ORIGEN;
        $leyenda = ClienteCuentacorrienteGrillaSupport::PREFIJO_LEYENDA_AJUSTE
            .date('d/m/Y', strtotime($fechaCorte));

        $monedaDolar = null;
        $cotCorte = null;
        $cotDolarCorte = ['valor' => 0.0, 'fecha' => null];
        if ($expresion === self::EXPRESION_PESOS) {
            $monedaDolar = $this->resolverMonedaDolar();
            $cotDolarCorte = CotizacionVigenteSupport::venta($fechaCorte, $monedaDolar['id']);
            $cotCorte = (float) $cotDolarCorte['valor'];
            if ($cotCorte <= 0) {
                throw new \RuntimeException(
                    'No hay cotización de dólar vigente al '.$fechaCorte.'. Sin eso no se puede armar el control en dólares ni el efecto del ajuste.'
                );
            }
        }

        $grupos = [];
        $resumen = [
            'movimientos' => 0,
            'grupos' => 0,
            'con_diferencia_pesos' => 0,
            'con_diferencia_dolares' => 0,
            'con_diferencia_origen' => 0,
            'ajustes_a_grabar' => 0,
            'ajustes_a_borrar' => 0,
            'filas_cotizacion_dia' => 0,
            'filas_sin_cotizacion' => 0,
            'filas_sin_dolar' => 0,
            'suma_diferencia_pesos' => 0.0,
            'suma_diferencia_dolares' => 0.0,
            'suma_diferencia_origen' => 0.0,
            'suma_ajuste_propuesto' => 0.0,
            'suma_dolares_despues' => 0.0,
        ];

        $abreviaturas = DB::table('moneda')->pluck('abreviatura', 'id')->map(
            static fn ($v) => strtoupper(trim((string) $v))
        )->all();

        $query = DB::table('cliente_cuentacorriente as cc')
            ->join('cliente as c', function ($join) {
                $join->on('c.id', '=', 'cc.cliente_id')->whereNull('c.deleted_at');
            })
            ->join('empresa as e', 'e.id', '=', 'cc.empresa_id')
            ->leftJoin('venta as v', 'v.id', '=', 'cc.venta_id')
            ->leftJoin('cobranza as cob', 'cob.id', '=', 'cc.cobranza_id')
            ->leftJoin('tipotransaccion_caja as ttc', 'ttc.id', '=', 'cob.tipotransaccion_caja_id')
            ->leftJoinSub(
                DB::table('cliente_cuentacorriente_aplicacion')
                    ->select('cliente_cuentacorriente_id')
                    ->selectRaw('SUM(total) as aplicado')
                    ->groupBy('cliente_cuentacorriente_id'),
                'apl',
                'apl.cliente_cuentacorriente_id',
                '=',
                'cc.id'
            )
            ->whereDate('cc.fecha', '<=', $fechaCorte)
            ->select([
                'cc.id',
                'cc.fecha',
                'cc.cliente_id',
                'cc.empresa_id',
                'cc.total',
                'cc.moneda_id',
                'cc.cotizacion',
                'cc.venta_id',
                'cc.cobranza_id',
                'cc.leyenda',
                'c.codigo as cliente_codigo',
                'c.nombre as cliente_nombre',
                'e.nombre as empresa_nombre',
                'v.codigo as venta_codigo',
                'ttc.abreviatura as cobranza_abreviatura',
                'apl.aplicado',
            ])
            ->orderBy('cc.id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('cc.empresa_id', $empresaId);
        }
        if ($clienteId !== null && $clienteId > 0) {
            $query->where('cc.cliente_id', $clienteId);
        }

        $query->chunkById(800, function ($filas) use (
            &$grupos,
            &$resumen,
            $leyenda,
            $monedaDolar,
            $expresion,
            $abreviaturas
        ) {
            foreach ($filas as $fila) {
                $resumen['movimientos']++;
                $monedaId = (int) ($fila->moneda_id ?: CotizacionVigenteSupport::MONEDA_LOCAL_ID);
                $key = $expresion === self::EXPRESION_ORIGEN
                    ? ((int) $fila->cliente_id).'|'.((int) $fila->empresa_id).'|'.$monedaId
                    : ((int) $fila->cliente_id).'|'.((int) $fila->empresa_id);
                if (! isset($grupos[$key])) {
                    $grupos[$key] = $this->grupoVacio($fila, $monedaId, $abreviaturas[$monedaId] ?? '');
                }

                $esAjuste = trim((string) ($fila->leyenda ?? '')) === $leyenda
                    && (int) ($fila->venta_id ?? 0) === 0
                    && (int) ($fila->cobranza_id ?? 0) === 0;

                if ($expresion === self::EXPRESION_ORIGEN) {
                    $this->acumularOrigen($grupos[$key], $fila, $esAjuste);

                    continue;
                }

                $pesosTotal = $this->aPesos($fila, $resumen, null, true);
                $usdTotal = $this->aDolares(
                    $pesosTotal,
                    $this->ymd($fila->fecha),
                    (int) $monedaDolar['id'],
                    $resumen,
                    true
                );

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

                $pendiente = ClienteCuentacorrienteGrillaSupport::saldoPendiente(
                    (float) $fila->total,
                    (float) ($fila->aplicado ?? 0)
                );
                $pesosPendiente = $this->aPesos($fila, $resumen, $pendiente, false);
                $usdPendiente = $this->aDolares(
                    $pesosPendiente,
                    $this->ymd($fila->fecha),
                    (int) $monedaDolar['id'],
                    $resumen,
                    false
                );
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
            $armado = $expresion === self::EXPRESION_ORIGEN
                ? $this->cerrarGrupoOrigen($grupo, $tolerancia)
                : $this->cerrarGrupo($grupo, (float) $cotCorte, $tolerancia);
            if ($armado === null) {
                continue;
            }
            $salida[] = $armado;
            if ($expresion === self::EXPRESION_ORIGEN) {
                $resumen['con_diferencia_origen'] += abs($armado['diferencia_origen']) >= $tolerancia ? 1 : 0;
                $resumen['suma_diferencia_origen'] += $armado['diferencia_origen'];
            } else {
                $resumen['con_diferencia_pesos'] += abs($armado['diferencia_pesos']) >= $tolerancia ? 1 : 0;
                $resumen['con_diferencia_dolares'] += abs($armado['diferencia_usd']) >= $tolerancia ? 1 : 0;
                $resumen['suma_diferencia_pesos'] += $armado['diferencia_pesos'];
                $resumen['suma_diferencia_dolares'] += $armado['diferencia_usd'];
                $resumen['suma_dolares_despues'] += $armado['diferencia_usd_despues'];
            }
            if ($armado['accion'] === 'grabar') {
                $resumen['ajustes_a_grabar']++;
                $resumen['suma_ajuste_propuesto'] += $armado['ajuste_propuesto'];
            } elseif ($armado['accion'] === 'borrar') {
                $resumen['ajustes_a_borrar']++;
            }
        }

        usort($salida, function (array $a, array $b) use ($expresion): int {
            $campo = $expresion === self::EXPRESION_ORIGEN ? 'diferencia_origen' : 'diferencia_pesos';

            return abs((float) $b[$campo]) <=> abs((float) $a[$campo]);
        });

        $resumen['grupos'] = count($salida);
        foreach ([
            'suma_diferencia_pesos',
            'suma_diferencia_dolares',
            'suma_diferencia_origen',
            'suma_ajuste_propuesto',
            'suma_dolares_despues',
        ] as $clave) {
            $resumen[$clave] = round((float) $resumen[$clave], 2);
        }

        return [
            'fecha' => $fechaCorte,
            'expresion' => $expresion,
            'leyenda' => $leyenda,
            'moneda_dolar_id' => $monedaDolar['id'] ?? null,
            'moneda_dolar_abreviatura' => $monedaDolar['abreviatura'] ?? null,
            'cotizacion_dolar_corte' => $cotCorte,
            'cotizacion_dolar_fecha' => $cotDolarCorte['fecha'] ?? null,
            'tolerancia' => $tolerancia,
            'resumen' => $resumen,
            'grupos' => $salida,
        ];
    }

    /**
     * @param  array<string, mixed>  $grupo
     */
    private function acumularOrigen(array &$grupo, object $fila, bool $esAjuste): void
    {
        $total = (float) $fila->total;
        $grupo['ficha_origen'] += $total;

        if ($esAjuste) {
            $grupo['ajuste_ids'][] = (int) $fila->id;
            $grupo['ajuste_actual'] += $total;

            return;
        }

        $grupo['ficha_origen_sin_ajuste'] += $total;

        if (! $this->esDeudaAbierta($fila)) {
            return;
        }

        $pendiente = ClienteCuentacorrienteGrillaSupport::saldoPendiente(
            $total,
            (float) ($fila->aplicado ?? 0)
        );
        $grupo['deuda_origen'] += $pendiente;
    }

    /**
     * @return array<string, mixed>
     */
    private function grupoVacio(object $fila, int $monedaId, string $abreviatura): array
    {
        return [
            'cliente_id' => (int) $fila->cliente_id,
            'cliente_codigo' => trim((string) ($fila->cliente_codigo ?? '')),
            'cliente_nombre' => trim((string) ($fila->cliente_nombre ?? '')),
            'empresa_id' => (int) $fila->empresa_id,
            'empresa_nombre' => trim((string) ($fila->empresa_nombre ?? '')),
            'moneda_id' => $monedaId,
            'moneda_abreviatura' => $abreviatura !== '' ? $abreviatura : 'ARS',
            'deuda_pesos' => 0.0,
            'ficha_pesos' => 0.0,
            'ficha_pesos_sin_ajuste' => 0.0,
            'deuda_usd' => 0.0,
            'ficha_usd' => 0.0,
            'ficha_usd_sin_ajuste' => 0.0,
            'deuda_origen' => 0.0,
            'ficha_origen' => 0.0,
            'ficha_origen_sin_ajuste' => 0.0,
            'ajuste_actual' => 0.0,
            'ajuste_ids' => [],
            'usd_incompleto' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $grupo
     * @return array<string, mixed>|null
     */
    private function cerrarGrupoOrigen(array $grupo, float $tolerancia): ?array
    {
        $deuda = round((float) $grupo['deuda_origen'], 2);
        $ficha = round((float) $grupo['ficha_origen'], 2);
        $ajusteActual = round((float) $grupo['ajuste_actual'], 2);
        $ajustePropuesto = round((float) $grupo['deuda_origen'] - (float) $grupo['ficha_origen_sin_ajuste'], 2);
        if (abs($ajustePropuesto) < $tolerancia) {
            $ajustePropuesto = 0.0;
        }

        $diferencia = round($deuda - $ficha, 2);
        $cambiaAjuste = abs($ajustePropuesto - $ajusteActual) >= $tolerancia
            || ($ajustePropuesto == 0.0 && $grupo['ajuste_ids'] !== []);
        $hayDiferencia = abs($diferencia) >= $tolerancia;
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
            'cliente_id' => $grupo['cliente_id'],
            'cliente_codigo' => $grupo['cliente_codigo'],
            'cliente_nombre' => $grupo['cliente_nombre'],
            'empresa_id' => $grupo['empresa_id'],
            'empresa_nombre' => $grupo['empresa_nombre'],
            'moneda_id' => $grupo['moneda_id'],
            'moneda_abreviatura' => $grupo['moneda_abreviatura'],
            'deuda_origen' => $deuda,
            'ficha_origen' => $ficha,
            'diferencia_origen' => $diferencia,
            'ajuste_actual' => $ajusteActual,
            'ajuste_propuesto' => $ajustePropuesto,
            'ajuste_ids' => $grupo['ajuste_ids'],
            'accion' => $accion,
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
            'cliente_id' => $grupo['cliente_id'],
            'cliente_codigo' => $grupo['cliente_codigo'],
            'cliente_nombre' => $grupo['cliente_nombre'],
            'empresa_id' => $grupo['empresa_id'],
            'empresa_nombre' => $grupo['empresa_nombre'],
            'moneda_id' => $grupo['moneda_id'],
            'moneda_abreviatura' => $grupo['moneda_abreviatura'],
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
        $pendiente = ClienteCuentacorrienteGrillaSupport::saldoPendiente($total, $aplicado);
        if (abs($pendiente) < 0.0001) {
            return false;
        }

        return ClienteCuentacorrienteDeudaAlcanceSupport::esComprobanteDeuda(
            isset($fila->venta_codigo) ? (string) $fila->venta_codigo : null,
            isset($fila->cobranza_abreviatura) ? (string) $fila->cobranza_abreviatura : null,
        );
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
