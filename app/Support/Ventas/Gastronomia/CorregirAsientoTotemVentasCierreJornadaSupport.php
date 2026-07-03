<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Backfill asiento 3 (totem_ventas_iva): haber ventas / IVA en cero por doble exclusión TOTEM al calcular importes.
 */
final class CorregirAsientoTotemVentasCierreJornadaSupport
{
    private const TOLERANCIA = 0.02;

    public function __construct(
        private readonly AsientoRepository $asientoRepository,
    ) {
    }

    /**
     * @return Collection<int, array{jornada: JornadaGastronomia, asiento_id: int, resumen_debe: float, resumen_haber: float}>
     */
    public function asientosTotemDesbalanceados(?int $empresaId = null): Collection
    {
        $desdePorEmpresa = (array) config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);

        $query = JornadaGastronomia::query()
            ->whereIn('empresa_id', array_keys($desdePorEmpresa))
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('gastronomia_cierre_jornada_proceso_snapshot as s')
                    ->whereColumn('s.jornada_gastronomia_id', 'jornada_gastronomia.id')
                    ->whereNotNull('s.payload');
            })
            ->orderBy('empresa_id')
            ->orderBy('fecha_jornada');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $out = collect();

        foreach ($query->get() as $jornada) {
            $desde = (string) ($desdePorEmpresa[(int) $jornada->empresa_id] ?? '');
            if ($desde === '' || (string) $jornada->fecha_jornada < $desde) {
                continue;
            }

            $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->orderByDesc('id')
                ->first();

            if ($snapshot === null) {
                continue;
            }

            foreach ($this->asientosDesdeSnapshot($snapshot) as $item) {
                if (($item['codigo'] ?? '') !== 'totem_ventas_iva') {
                    continue;
                }

                $asientoId = (int) ($item['asiento_id'] ?? 0);
                if ($asientoId <= 0) {
                    continue;
                }

                $totales = $this->totalesMovimientosAsiento($asientoId);
                $debe = (float) ($totales['debe'] ?? 0);
                $haber = (float) ($totales['haber'] ?? 0);

                if ($debe <= self::TOLERANCIA) {
                    continue;
                }

                if ($haber + self::TOLERANCIA >= $debe) {
                    continue;
                }

                $out->push([
                    'jornada' => $jornada,
                    'asiento_id' => $asientoId,
                    'resumen_debe' => $debe,
                    'resumen_haber' => $haber,
                ]);
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   asientos:int,
     *   lineas_erp:int,
     *   ctamov:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    public function ejecutar(bool $dryRun = false, ?int $empresaId = null): array
    {
        $resultado = [
            'asientos' => 0,
            'lineas_erp' => 0,
            'ctamov' => 0,
            'ya_ok' => 0,
            'errores' => [],
        ];

        foreach ($this->asientosTotemDesbalanceados($empresaId) as $item) {
            /** @var JornadaGastronomia $jornada */
            $jornada = $item['jornada'];
            $asientoId = (int) $item['asiento_id'];
            $empresa = (int) $jornada->empresa_id;
            $fecha = (string) $jornada->fecha_jornada;
            $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresa);

            $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaSoloTotem($empresa, $fecha);
            if (round((float) ($datos['total'] ?? 0), 2) <= self::TOLERANCIA) {
                $resultado['errores'][] = 'Jornada '.$fecha.' empresa '.$empresa.' asiento #'.$asientoId
                    .': sin facturación TOTEM para recalcular haber.';
                continue;
            }

            try {
                $cambios = $this->actualizarAsientoTotemVentasIva($asientoId, $datos, $config, $dryRun);
                if ($cambios['lineas'] > 0 || $cambios['ctamov']) {
                    $resultado['asientos']++;
                    $resultado['lineas_erp'] += $cambios['lineas'];
                    if ($cambios['ctamov']) {
                        $resultado['ctamov']++;
                    }
                } else {
                    $resultado['ya_ok']++;
                }
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Jornada '.$fecha.' empresa '.$empresa.' asiento #'.$asientoId.': '.$e->getMessage();
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $config
     * @return array{lineas:int,ctamov:bool}
     */
    private function actualizarAsientoTotemVentasIva(int $asientoId, array $datos, array $config, bool $dryRun): array
    {
        $asiento = Asiento::query()->with(['asiento_movimientos.cuentacontables'])->find($asientoId);
        if ($asiento === null) {
            throw new RuntimeException('Asiento ERP no encontrado.');
        }

        $cuentaVentasId = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cuentaIvaId = (int) ($config['cuenta_iva_id'] ?? 0);
        $cuentaKioscoId = (int) ($config['cuenta_ventas_kiosco_id'] ?? 0);

        $haberEsperadoPorCuenta = $this->haberEsperadoPorCuenta($datos, $cuentaVentasId, $cuentaKioscoId, $cuentaIvaId);
        if ($haberEsperadoPorCuenta === []) {
            throw new RuntimeException('Importes haber TOTEM esperados en cero.');
        }

        $totemContableId = $this->cuentacontableTotemEmpresa((int) $asiento->empresa_id);
        $lineasActualizadas = 0;
        $requiereCtamov = false;

        /** @var array<int, list<Asiento_Movimiento>> $movimientosHaberPorCuenta */
        $movimientosHaberPorCuenta = [];
        foreach ($asiento->asiento_movimientos as $mov) {
            $monto = round((float) ($mov->monto ?? 0), 2);
            $cuentaId = (int) ($mov->cuentacontable_id ?? 0);
            if ($monto > self::TOLERANCIA || $cuentaId <= 0) {
                continue;
            }
            if ($totemContableId > 0 && $cuentaId === $totemContableId) {
                continue;
            }
            $movimientosHaberPorCuenta[$cuentaId][] = $mov;
        }

        foreach ($haberEsperadoPorCuenta as $cuentaId => $importeHaber) {
            $montoEsperado = $this->montoDesdeImporteContable($importeHaber);
            $movs = $movimientosHaberPorCuenta[$cuentaId] ?? [];

            if ($movs === []) {
                throw new RuntimeException('Falta línea haber en cuenta contable id '.$cuentaId.'.');
            }

            /** @var Asiento_Movimiento $principal */
            $principal = $movs[0];
            $montoActual = round((float) $principal->monto, 2);

            if (abs($montoActual - $montoEsperado) > self::TOLERANCIA) {
                if (! $dryRun) {
                    $principal->monto = $montoEsperado;
                    if ($cuentaId > 0) {
                        $principal->cuentacontable_id = $cuentaId;
                    }
                    $principal->save();
                }
                $lineasActualizadas++;
                $requiereCtamov = true;
            }

            for ($i = 1, $n = count($movs); $i < $n; $i++) {
                $extra = $movs[$i];
                if (abs((float) $extra->monto) <= self::TOLERANCIA) {
                    continue;
                }
                if (! $dryRun) {
                    $extra->delete();
                }
                $lineasActualizadas++;
                $requiereCtamov = true;
            }
        }

        if (! $dryRun && $requiereCtamov) {
            $this->validarCuadreAsiento($asientoId, (float) ($datos['total'] ?? 0));
            $this->aplicarDescripcionEstandarAsiento($asiento);
            $asiento->refresh()->load(['asiento_movimientos.monedas']);
            $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
            $this->asientoRepository->sincronizarCtamovAnita($payload);
        }

        return [
            'lineas' => $lineasActualizadas,
            'ctamov' => $requiereCtamov && ! $dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<int, float>
     */
    private function haberEsperadoPorCuenta(
        array $datos,
        int $cuentaVentasId,
        int $cuentaKioscoId,
        int $cuentaIvaId,
    ): array {
        $map = [];

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

    private function cuentacontableTotemEmpresa(int $empresaId): int
    {
        $totemCajaId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        if ($totemCajaId <= 0) {
            return 0;
        }

        $cache = [];
        $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);

        return CierreJornadaProcesoAsientosGrabacionSupport::resolverCuentacontableId(
            $totemCajaId,
            $empresaId,
            $config,
            $cache,
        );
    }

    private function validarCuadreAsiento(int $asientoId, float $totalEsperado): void
    {
        $totales = $this->totalesMovimientosAsiento($asientoId);
        $debe = round((float) ($totales['debe'] ?? 0), 2);
        $haber = round((float) ($totales['haber'] ?? 0), 2);
        $totalEsperado = round($totalEsperado, 2);

        if (abs($debe - $haber) > self::TOLERANCIA || abs($debe - $totalEsperado) > self::TOLERANCIA) {
            throw new RuntimeException(
                'Asiento #'.$asientoId.' no cuadra tras corrección (debe '.$debe.', haber '.$haber.').',
            );
        }
    }

    /**
     * @return array{debe: float, haber: float}
     */
    private function totalesMovimientosAsiento(int $asientoId): array
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

    private function aplicarDescripcionEstandarAsiento(Asiento $asiento): void
    {
        $descripcion = CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO;

        if (trim((string) ($asiento->observacion ?? '')) !== $descripcion) {
            $asiento->observacion = $descripcion;
            $asiento->save();
        }

        Asiento_Movimiento::query()
            ->where('asiento_id', $asiento->id)
            ->where('observacion', '!=', $descripcion)
            ->update(['observacion' => $descripcion]);
    }

    private function montoDesdeImporteContable(float $importe): float
    {
        if (abs($importe) <= 0.0001) {
            return 0.0;
        }

        if ($importe > 0) {
            return -round($importe, 2);
        }

        return round(abs($importe), 2);
    }

    /**
     * @return list<array{codigo:string,asiento_id:int}>
     */
    private function asientosDesdeSnapshot(GastronomiaCierreJornadaProcesoSnapshot $snapshot): array
    {
        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? [];
        if (! is_array($asientos)) {
            return [];
        }

        $out = [];
        foreach ($asientos as $item) {
            if (! is_array($item)) {
                continue;
            }
            $asientoId = (int) ($item['asiento_id'] ?? 0);
            if ($asientoId <= 0) {
                continue;
            }
            $out[] = [
                'codigo' => (string) ($item['codigo'] ?? ''),
                'asiento_id' => $asientoId,
            ];
        }

        return $out;
    }
}
