<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Contable\AsientoRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recuadra asientos 3/4 TOTEM al total de la venta ERP (no al cobro Waitry) y resincroniza ctamov.
 */
final class CorregirImporteAsientoTotemVsVentaSupport
{
    private const TOLERANCIA = 0.02;

    /** Puente de cobranzas QR/tótem. No es el medio real del informe Z. */
    private const CUENTA_PUENTE_TOTEM = '113010010';

    /** Contrapartida del cobro que ya no tiene venta. No es un medio (no entra al auditor). */
    private const CUENTA_PARTIDAS_PENDIENTES = '211010018';

    /** @var list<string> */
    private const CODIGOS = ['totem_ventas_iva', 'totem_puente'];

    public function __construct(
        private readonly AsientoRepository $asientoRepository,
    ) {
    }

    /**
     * @return array{
     *   empresa_id:int,
     *   fecha_jornada:string,
     *   jornada_id:int,
     *   total_venta_erp:float,
     *   asientos:list<array<string, mixed>>,
     *   requiere_cambio:bool
     * }
     */
    public function planificar(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            throw new RuntimeException('Empresa y fecha de jornada son obligatorios.');
        }

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->first();
        if ($jornada === null) {
            throw new RuntimeException('No hay jornada gastronomía empresa '.$empresaId.' fecha '.$fechaJornada.'.');
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornada->id)
            ->orderByDesc('id')
            ->first();
        if ($snapshot === null) {
            throw new RuntimeException('No hay snapshot de cierre de jornada '.$jornada->id.'.');
        }

        $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaSoloTotem($empresaId, $fechaJornada);
        $totalErp = round((float) ($datos['total'] ?? 0), 2);
        $cantidadEmisiones = (int) ($datos['cantidad_emisiones'] ?? 0);
        // Neto 0 con factura y nota de crédito: el asiento de ventas queda en cero.
        // El puente conserva el cobro del medio (informe Z) y la diferencia va a partidas pendientes.
        // Sin emisiones no hay base para recuadrar.
        if ($totalErp <= self::TOLERANCIA && $cantidadEmisiones === 0) {
            throw new RuntimeException('No hay facturación TOTEM ERP para recalcular (empresa '.$empresaId.', '.$fechaJornada.').');
        }

        $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $haberPorCuenta = $this->haberEsperadoPorCuenta($datos, $config);
        $ids = $this->asientoIdsPorCodigo($snapshot);
        $asientos = [];
        $requiereCambio = false;

        foreach (self::CODIGOS as $codigo) {
            $asientoId = (int) ($ids[$codigo] ?? 0);
            if ($asientoId <= 0) {
                throw new RuntimeException('Falta asiento '.$codigo.' en el snapshot de la jornada.');
            }

            $detalle = $this->planAsiento($asientoId, $codigo, $totalErp, $haberPorCuenta);
            if ($detalle['requiere_cambio']) {
                $requiereCambio = true;
            }
            $asientos[] = $detalle;
        }

        return [
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'jornada_id' => (int) $jornada->id,
            'snapshot_id' => (int) $snapshot->id,
            'total_venta_erp' => $totalErp,
            'cantidad_facturas' => (int) ($datos['cantidad_emisiones'] ?? 0),
            'asientos' => $asientos,
            'requiere_cambio' => $requiereCambio,
        ];
    }

    /**
     * @return array{asientos:int,lineas_erp:int,ctamov:int,ya_ok:int,errores:list<string>,plan:array<string, mixed>}
     */
    /**
     * Después de una nota de crédito: si la jornada ya tiene asientos de tótem, los deja
     * en el neto facturado. Sin cierre todavía, no hace nada.
     */
    public function recuadrarTrasNotaCredito(int $empresaId, string $fechaJornada): void
    {
        try {
            $resultado = $this->ejecutar($empresaId, $fechaJornada, false);
        } catch (RuntimeException $e) {
            if (self::omisionEsperadaTrasNotaCredito($e->getMessage())) {
                return;
            }

            throw $e;
        }

        if ($resultado['errores'] !== []) {
            throw new RuntimeException(implode(' ', $resultado['errores']));
        }
    }

    private static function omisionEsperadaTrasNotaCredito(string $mensaje): bool
    {
        return str_contains($mensaje, 'No hay snapshot')
            || str_contains($mensaje, 'No hay jornada')
            || str_contains($mensaje, 'No hay facturación TOTEM');
    }

    public function ejecutar(int $empresaId, string $fechaJornada, bool $dryRun = true): array
    {
        $plan = $this->planificar($empresaId, $fechaJornada);
        $resultado = [
            'asientos' => 0,
            'lineas_erp' => 0,
            'ctamov' => 0,
            'ya_ok' => 0,
            'errores' => [],
            'plan' => $plan,
        ];

        if (! $plan['requiere_cambio']) {
            $resultado['ya_ok'] = count($plan['asientos']);

            return $resultado;
        }

        if ($dryRun) {
            foreach ($plan['asientos'] as $asiento) {
                if ($asiento['requiere_cambio']) {
                    $resultado['asientos']++;
                    $resultado['lineas_erp'] += count($asiento['cambios']);
                } else {
                    $resultado['ya_ok']++;
                }
            }

            return $resultado;
        }

        try {
            DB::transaction(function () use ($plan, &$resultado): void {
                foreach ($plan['asientos'] as $asientoPlan) {
                    $cambios = $this->aplicarCambiosAsiento($asientoPlan);
                    if ($cambios === 0) {
                        $resultado['ya_ok']++;

                        continue;
                    }
                    $resultado['asientos']++;
                    $resultado['lineas_erp'] += $cambios;
                    $this->validarCuadre((int) $asientoPlan['asiento_id'], (float) $asientoPlan['total_esperado']);
                    if (abs((float) $asientoPlan['total_esperado']) <= self::TOLERANCIA) {
                        $this->asientoRepository->eliminarCtamovAnitaPorNumero(
                            (int) $asientoPlan['empresa_id'],
                            (string) $asientoPlan['numeroasiento'],
                        );
                    } else {
                        $this->sincronizarCtamov((int) $asientoPlan['asiento_id']);
                    }
                    $resultado['ctamov']++;
                }
                $this->actualizarTotalesSnapshot((int) $plan['snapshot_id'], $plan);
            });
        } catch (RuntimeException $e) {
            $resultado['errores'][] = $e->getMessage();
        }

        return $resultado;
    }

    /**
     * @param  array<int, float>  $haberPorCuenta
     * @return array<string, mixed>
     */
    private function planAsiento(int $asientoId, string $codigo, float $totalErp, array $haberPorCuenta): array
    {
        $asiento = Asiento::query()->with(['asiento_movimientos.cuentacontables'])->find($asientoId);
        if ($asiento === null) {
            throw new RuntimeException('Asiento ERP #'.$asientoId.' no encontrado.');
        }

        if ($codigo === 'totem_puente') {
            return $this->planPuente($asiento, $totalErp);
        }

        $cambios = [];
        foreach ($asiento->asiento_movimientos as $mov) {
            $montoActual = round((float) ($mov->monto ?? 0), 2);
            $cuentaId = (int) ($mov->cuentacontable_id ?? 0);
            $esperado = $this->montoEsperadoLinea($codigo, $montoActual, $cuentaId, $totalErp, $haberPorCuenta);
            if (abs($montoActual - $esperado) <= self::TOLERANCIA) {
                continue;
            }
            $cta = $mov->cuentacontables;
            $cambios[] = [
                'movimiento_id' => (int) $mov->id,
                'cuentacontable_id' => $cuentaId,
                'cuenta' => trim((string) ($cta->codigo ?? '')).' '.trim((string) ($cta->nombre ?? '')),
                'monto_actual' => $montoActual,
                'monto_esperado' => $esperado,
            ];
        }

        return $this->armarPlanAsiento($asiento, $codigo, $totalErp, $cambios);
    }

    /**
     * El débito de Mercado Pago (y el resto de medios reales) queda en el cobro ya contabilizado.
     * El crédito al puente tótem baja al neto de la venta. La diferencia se acredita a partidas pendientes.
     *
     * @return array<string, mixed>
     */
    private function planPuente(Asiento $asiento, float $totalErp): array
    {
        $partidasId = (int) Cuentacontable::query()
            ->where('empresa_id', (int) $asiento->empresa_id)
            ->where('codigo', self::CUENTA_PARTIDAS_PENDIENTES)
            ->value('id');
        if ($partidasId <= 0) {
            throw new RuntimeException(
                'Falta la cuenta '.self::CUENTA_PARTIDAS_PENDIENTES.' en la empresa '.$asiento->empresa_id.'.',
            );
        }

        $cobro = 0.0;
        $partidasMov = null;
        $cambios = [];
        foreach ($asiento->asiento_movimientos as $mov) {
            $cta = $mov->cuentacontables;
            $codigoCuenta = trim((string) ($cta->codigo ?? ''));
            $montoActual = round((float) ($mov->monto ?? 0), 2);
            $cuentaId = (int) ($mov->cuentacontable_id ?? 0);

            if ($codigoCuenta === self::CUENTA_PARTIDAS_PENDIENTES) {
                $partidasMov = $mov;
                continue;
            }

            if ($codigoCuenta === self::CUENTA_PUENTE_TOTEM) {
                $esperado = round(-1 * $totalErp, 2);
                if (abs($montoActual - $esperado) > self::TOLERANCIA) {
                    $cambios[] = $this->cambioLinea($mov, $esperado);
                }
                continue;
            }

            if ($montoActual > self::TOLERANCIA) {
                $cobro = round($cobro + $montoActual, 2);
            }
        }

        $gap = round($cobro - $totalErp, 2);
        if ($gap < -self::TOLERANCIA) {
            throw new RuntimeException(
                'Asiento puente #'.$asiento->id.' tiene cobro '.$cobro.' menor que la venta neta '.$totalErp.'.',
            );
        }

        $esperadoPartidas = round(-1 * max($gap, 0.0), 2);
        if ($partidasMov !== null) {
            $actual = round((float) $partidasMov->monto, 2);
            if (abs($actual - $esperadoPartidas) > self::TOLERANCIA) {
                $cambios[] = $this->cambioLinea($partidasMov, $esperadoPartidas);
            }
        } elseif ($esperadoPartidas < -self::TOLERANCIA) {
            $cambios[] = [
                'movimiento_id' => 0,
                'insertar' => true,
                'asiento_id' => (int) $asiento->id,
                'cuentacontable_id' => $partidasId,
                'cuenta' => self::CUENTA_PARTIDAS_PENDIENTES.' PARTID.PENDIENTES D IMPUTACION',
                'monto_actual' => 0.0,
                'monto_esperado' => $esperadoPartidas,
            ];
        }

        return $this->armarPlanAsiento($asiento, 'totem_puente', round(max($cobro, 0.0), 2), $cambios);
    }

    /**
     * @return array{movimiento_id:int,cuentacontable_id:int,cuenta:string,monto_actual:float,monto_esperado:float}
     */
    private function cambioLinea(Asiento_Movimiento $mov, float $esperado): array
    {
        $cta = $mov->cuentacontables;

        return [
            'movimiento_id' => (int) $mov->id,
            'cuentacontable_id' => (int) ($mov->cuentacontable_id ?? 0),
            'cuenta' => trim((string) ($cta->codigo ?? '')).' '.trim((string) ($cta->nombre ?? '')),
            'monto_actual' => round((float) ($mov->monto ?? 0), 2),
            'monto_esperado' => round($esperado, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cambios
     * @return array<string, mixed>
     */
    private function armarPlanAsiento(Asiento $asiento, string $codigo, float $totalEsperado, array $cambios): array
    {
        $totales = $this->totalesMovimientos((int) $asiento->id);

        return [
            'codigo' => $codigo,
            'asiento_id' => (int) $asiento->id,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'anita_nro_asiento' => $asiento->anita_nro_asiento,
            'empresa_id' => (int) $asiento->empresa_id,
            'fecha' => (string) $asiento->fecha,
            'debe_actual' => $totales['debe'],
            'haber_actual' => $totales['haber'],
            'total_esperado' => round($totalEsperado, 2),
            'requiere_cambio' => $cambios !== [],
            'cambios' => $cambios,
        ];
    }

    /**
     * @param  array<int, float>  $haberPorCuenta
     */
    private function montoEsperadoLinea(
        string $codigo,
        float $montoActual,
        int $cuentaId,
        float $totalErp,
        array $haberPorCuenta,
    ): float {
        if (abs($totalErp) <= self::TOLERANCIA) {
            return 0.0;
        }

        if ($codigo === 'totem_puente') {
            return $montoActual >= 0 ? $totalErp : -1 * $totalErp;
        }

        if ($montoActual > self::TOLERANCIA) {
            return $totalErp;
        }

        if (isset($haberPorCuenta[$cuentaId])) {
            return -1 * round((float) $haberPorCuenta[$cuentaId], 2);
        }

        throw new RuntimeException(
            'Línea haber cuenta '.$cuentaId.' del asiento TOTEM ventas/IVA no tiene importe esperado.',
        );
    }

    /**
     * @param  array<string, mixed>  $asientoPlan
     */
    private function aplicarCambiosAsiento(array $asientoPlan): int
    {
        $n = 0;
        foreach ($asientoPlan['cambios'] as $cambio) {
            if (! empty($cambio['insertar'])) {
                Asiento_Movimiento::query()->create([
                    'asiento_id' => (int) $cambio['asiento_id'],
                    'cuentacontable_id' => (int) $cambio['cuentacontable_id'],
                    'monto' => round((float) $cambio['monto_esperado'], 2),
                    'moneda_id' => 1,
                    'cotizacion' => 1,
                    'observacion' => 'Venta gastronomia',
                ]);
                $n++;
                continue;
            }
            $mov = Asiento_Movimiento::query()->find((int) $cambio['movimiento_id']);
            if ($mov === null) {
                throw new RuntimeException('Movimiento #'.$cambio['movimiento_id'].' no encontrado.');
            }
            $mov->monto = round((float) $cambio['monto_esperado'], 2);
            $mov->save();
            $n++;
        }

        return $n;
    }

    private function sincronizarCtamov(int $asientoId): void
    {
        $asiento = Asiento::query()->with(['asiento_movimientos.monedas'])->find($asientoId);
        if ($asiento === null) {
            throw new RuntimeException('Asiento #'.$asientoId.' no encontrado para ctamov.');
        }
        $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
        $this->asientoRepository->sincronizarCtamovAnita($payload);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function actualizarTotalesSnapshot(int $snapshotId, array $plan): void
    {
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()->find($snapshotId);
        if ($snapshot === null) {
            return;
        }
        $porCodigo = [];
        foreach ($plan['asientos'] ?? [] as $asientoPlan) {
            if (! is_array($asientoPlan)) {
                continue;
            }
            $porCodigo[(string) ($asientoPlan['codigo'] ?? '')] = round((float) ($asientoPlan['total_esperado'] ?? 0), 2);
        }
        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? [];
        if (! is_array($asientos)) {
            return;
        }
        foreach ($asientos as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $codigo = (string) ($item['codigo'] ?? '');
            if (! array_key_exists($codigo, $porCodigo)) {
                continue;
            }
            $total = $porCodigo[$codigo];
            $payload['asientos_proceso_grabacion']['asientos'][$i]['resumen_debe'] = $total;
            $payload['asientos_proceso_grabacion']['asientos'][$i]['resumen_haber'] = $total;
            if (isset($item['total']) || array_key_exists('total', $item)) {
                $payload['asientos_proceso_grabacion']['asientos'][$i]['total'] = $total;
            }
        }
        $snapshot->payload = $payload;
        $snapshot->save();
    }

    /**
     * @return array<string, int>
     */
    private function asientoIdsPorCodigo(GastronomiaCierreJornadaProcesoSnapshot $snapshot): array
    {
        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? [];
        $out = [];
        foreach ((array) $asientos as $item) {
            if (! is_array($item)) {
                continue;
            }
            $codigo = (string) ($item['codigo'] ?? '');
            $id = (int) ($item['asiento_id'] ?? 0);
            if ($codigo !== '' && $id > 0) {
                $out[$codigo] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $config
     * @return array<int, float>
     */
    private function haberEsperadoPorCuenta(array $datos, array $config): array
    {
        $map = [];
        $cuentaVentasId = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cuentaIvaId = (int) ($config['cuenta_iva_id'] ?? 0);
        $cuentaKioscoId = (int) ($config['cuenta_ventas_kiosco_id'] ?? 0);

        $ventasGravadas = round((float) ($datos['ventas_gravadas'] ?? 0), 2);
        if ($cuentaVentasId > 0 && abs($ventasGravadas) > self::TOLERANCIA) {
            $map[$cuentaVentasId] = round(($map[$cuentaVentasId] ?? 0) + $ventasGravadas, 2);
        }

        $ventasKiosco = round((float) ($datos['ventas_kiosco'] ?? 0), 2);
        if ($cuentaKioscoId > 0 && abs($ventasKiosco) > self::TOLERANCIA) {
            $map[$cuentaKioscoId] = round(($map[$cuentaKioscoId] ?? 0) + $ventasKiosco, 2);
        }

        $ivaTotal = round((float) ($datos['iva_normal'] ?? 0) + (float) ($datos['iva_cigarrillos'] ?? 0), 2);
        if ($cuentaIvaId > 0 && abs($ivaTotal) > self::TOLERANCIA) {
            $map[$cuentaIvaId] = round(($map[$cuentaIvaId] ?? 0) + $ivaTotal, 2);
        }

        return $map;
    }

    private function validarCuadre(int $asientoId, float $totalEsperado): void
    {
        $totales = $this->totalesMovimientos($asientoId);
        $debe = $totales['debe'];
        $haber = $totales['haber'];
        $totalEsperado = round($totalEsperado, 2);
        if (abs($debe - $haber) > self::TOLERANCIA || abs($debe - $totalEsperado) > self::TOLERANCIA) {
            throw new RuntimeException(
                'Asiento #'.$asientoId.' no cuadra tras corrección (debe '.$debe.', haber '.$haber.', esperado '.$totalEsperado.').',
            );
        }
    }

    /**
     * @return array{debe:float,haber:float}
     */
    private function totalesMovimientos(int $asientoId): array
    {
        $debe = (float) DB::table('asiento_movimiento')
            ->where('asiento_id', $asientoId)
            ->where('monto', '>', 0)
            ->sum('monto');
        $haber = (float) DB::table('asiento_movimiento')
            ->where('asiento_id', $asientoId)
            ->where('monto', '<', 0)
            ->sum(DB::raw('ABS(monto)'));

        return [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
        ];
    }
}
