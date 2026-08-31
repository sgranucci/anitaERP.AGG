<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
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
        if ($totalErp <= self::TOLERANCIA) {
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
                    $this->validarCuadre((int) $asientoPlan['asiento_id'], (float) $plan['total_venta_erp']);
                    $this->sincronizarCtamov((int) $asientoPlan['asiento_id']);
                    $resultado['ctamov']++;
                }
                $this->actualizarTotalesSnapshot((int) $plan['snapshot_id'], (float) $plan['total_venta_erp']);
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

        $totales = $this->totalesMovimientos($asientoId);

        return [
            'codigo' => $codigo,
            'asiento_id' => $asientoId,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'anita_nro_asiento' => $asiento->anita_nro_asiento,
            'empresa_id' => (int) $asiento->empresa_id,
            'fecha' => (string) $asiento->fecha,
            'debe_actual' => $totales['debe'],
            'haber_actual' => $totales['haber'],
            'total_esperado' => $totalErp,
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

    private function actualizarTotalesSnapshot(int $snapshotId, float $totalErp): void
    {
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()->find($snapshotId);
        if ($snapshot === null) {
            return;
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
            if (! in_array((string) ($item['codigo'] ?? ''), self::CODIGOS, true)) {
                continue;
            }
            $payload['asientos_proceso_grabacion']['asientos'][$i]['resumen_debe'] = $totalErp;
            $payload['asientos_proceso_grabacion']['asientos'][$i]['resumen_haber'] = $totalErp;
            if (isset($item['total']) || array_key_exists('total', $item)) {
                $payload['asientos_proceso_grabacion']['asientos'][$i]['total'] = $totalErp;
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
