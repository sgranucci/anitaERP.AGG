<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Backfill ventas cigarrillos (ex kiosco): importes corregidos en facturas mixtas + cuenta 414020001.
 * ERP asiento_movimiento + Anita ctamov (delete + insert vía AsientoRepository).
 */
final class CorregirVentasCigarrillosAsientoCierreJornadaSupport
{
    private const TOLERANCIA = 0.02;

    /** @var list<string> */
    private const CODIGOS_ASIENTO = ['ventas_medio_real', 'totem_ventas_iva'];

    public function __construct(
        private readonly AsientoRepository $asientoRepository,
    ) {
    }

    /**
     * @return Collection<int, JornadaGastronomia>
     */
    public function jornadasAfectadas(?int $empresaId = null): Collection
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

        return $query->get()->filter(function (JornadaGastronomia $jornada) use ($desdePorEmpresa): bool {
            $desde = (string) ($desdePorEmpresa[(int) $jornada->empresa_id] ?? '');

            return $desde !== '' && (string) $jornada->fecha_jornada >= $desde;
        })->values();
    }

    /**
     * @return array{
     *   jornadas:int,
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
            'jornadas' => 0,
            'asientos' => 0,
            'lineas_erp' => 0,
            'ctamov' => 0,
            'ya_ok' => 0,
            'errores' => [],
        ];

        foreach ($this->jornadasAfectadas($empresaId) as $jornada) {
            $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->orderByDesc('id')
                ->first();

            if ($snapshot === null) {
                continue;
            }

            $asientosSnapshot = $this->asientosDesdeSnapshot($snapshot);
            if ($asientosSnapshot === []) {
                continue;
            }

            $resultado['jornadas']++;
            $empresa = (int) $jornada->empresa_id;
            $fecha = (string) $jornada->fecha_jornada;
            $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresa);

            foreach ($asientosSnapshot as $item) {
                $codigo = (string) ($item['codigo'] ?? '');
                $asientoId = (int) ($item['asiento_id'] ?? 0);
                if ($asientoId <= 0 || ! in_array($codigo, self::CODIGOS_ASIENTO, true)) {
                    continue;
                }

                $datos = $codigo === 'totem_ventas_iva'
                    ? CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaSoloTotem($empresa, $fecha)
                    : CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresa, $fecha);

                try {
                    $cambios = $this->actualizarAsientoVentas(
                        $asientoId,
                        $datos,
                        $config,
                        $dryRun,
                    );
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
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $config
     * @return array{lineas:int,ctamov:bool}
     */
    private function actualizarAsientoVentas(int $asientoId, array $datos, array $config, bool $dryRun): array
    {
        $asiento = Asiento::query()->with(['asiento_movimientos.cuentacontables'])->find($asientoId);
        if ($asiento === null) {
            throw new RuntimeException('Asiento ERP no encontrado.');
        }

        $cuentaVentasId = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cuentaIvaId = (int) ($config['cuenta_iva_id'] ?? 0);
        $cuentaKioscoId = (int) ($config['cuenta_ventas_kiosco_id'] ?? 0);

        $esperados = [
            'ventas_gravadas' => round((float) ($datos['ventas_gravadas'] ?? 0), 2),
            'ventas_kiosco' => round((float) ($datos['ventas_kiosco'] ?? 0), 2),
            'iva_normal' => round((float) ($datos['iva_normal'] ?? 0), 2),
            'iva_cigarrillos' => round((float) ($datos['iva_cigarrillos'] ?? 0), 2),
        ];

        $mapeo = [
            'ventas_gravadas' => [
                'match' => static fn (string $obs): bool => str_starts_with($obs, 'Ventas gravadas'),
                'cuenta_id' => $cuentaVentasId,
            ],
            'ventas_kiosco' => [
                'match' => static fn (string $obs): bool => str_starts_with($obs, 'Ventas kiosco'),
                'cuenta_id' => $cuentaKioscoId,
            ],
            'iva_normal' => [
                'match' => static fn (string $obs): bool => str_starts_with($obs, 'IVA débito fiscal')
                    && ! str_contains($obs, 'cigarrillos / kiosco'),
                'cuenta_id' => $cuentaIvaId,
            ],
            'iva_cigarrillos' => [
                'match' => static fn (string $obs): bool => str_contains($obs, 'cigarrillos / kiosco'),
                'cuenta_id' => $cuentaIvaId,
            ],
        ];

        $lineasActualizadas = 0;
        $requiereCtamov = false;

        /** @var Asiento_Movimiento $mov */
        foreach ($asiento->asiento_movimientos as $mov) {
            $obs = trim((string) ($mov->observacion ?? ''));
            foreach ($mapeo as $clave => $meta) {
                if (! ($meta['match'])($obs)) {
                    continue;
                }

                $importeEsperado = $esperados[$clave];
                $montoEsperado = $this->montoDesdeImporteContable($importeEsperado);
                $montoActual = round((float) $mov->monto, 2);
                $cuentaEsperada = (int) $meta['cuenta_id'];

                if (abs($montoActual - $montoEsperado) <= self::TOLERANCIA
                    && (int) $mov->cuentacontable_id === $cuentaEsperada) {
                    break;
                }

                if (! $dryRun) {
                    $mov->monto = $montoEsperado;
                    if ($cuentaEsperada > 0) {
                        $mov->cuentacontable_id = $cuentaEsperada;
                    }
                    $mov->save();
                }

                $lineasActualizadas++;
                $requiereCtamov = true;
                break;
            }
        }

        if ($requiereCtamov && ! $dryRun) {
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
