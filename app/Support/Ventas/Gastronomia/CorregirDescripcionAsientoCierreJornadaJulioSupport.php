<?php

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Backfill: leyenda «Venta gastronomia» en asientos del cierre Waitry post jornada (ERP + Anita ctamov).
 * Alcance: jornadas con fecha_jornada en julio del año indicado.
 */
final class CorregirDescripcionAsientoCierreJornadaJulioSupport
{
    public const MES = 7;

    private const PREFIJO_LEGACY = 'Cierre Waitry jornada ';

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectadosDesdeConfig(?int $empresaId = null): Collection
    {
        $desdePorEmpresa = (array) config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);

        $jornadaQuery = JornadaGastronomia::query()
            ->whereIn('empresa_id', array_keys($desdePorEmpresa));

        if ($empresaId !== null && $empresaId > 0) {
            $jornadaQuery->where('empresa_id', $empresaId);
        }

        $jornadas = $jornadaQuery->get()->filter(function (JornadaGastronomia $jornada) use ($desdePorEmpresa): bool {
            $desde = (string) ($desdePorEmpresa[(int) $jornada->empresa_id] ?? '');

            return $desde !== '' && (string) $jornada->fecha_jornada >= $desde;
        });

        $ids = collect();
        foreach ($jornadas as $jornada) {
            $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->orderByDesc('id')
                ->first();

            if ($snapshot === null) {
                continue;
            }

            foreach ($this->asientoIdsDesdeSnapshot($snapshot) as $asientoId) {
                $ids->push($asientoId);
            }
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = Asiento::query()->whereIn('id', $ids->unique()->all())->orderBy('id');
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(int $anio, ?int $empresaId = null): Collection
    {
        $ids = $this->asientoIdsJulio($anio, $empresaId);
        if ($ids === []) {
            return collect();
        }

        $query = Asiento::query()->whereIn('id', $ids)->orderBy('id');
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
    }

    /**
     * @return list<int>
     */
    public function asientoIdsJulio(int $anio, ?int $empresaId = null): array
    {
        $jornadaQuery = JornadaGastronomia::query()
            ->whereYear('fecha_jornada', $anio)
            ->whereMonth('fecha_jornada', self::MES);

        if ($empresaId !== null && $empresaId > 0) {
            $jornadaQuery->where('empresa_id', $empresaId);
        }

        $jornadaIds = $jornadaQuery->pluck('id');

        $idsSnapshot = collect();
        if ($jornadaIds->isNotEmpty()) {
            $snapshots = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->whereIn('jornada_gastronomia_id', $jornadaIds)
                ->get(['id', 'jornada_gastronomia_id', 'payload']);

            foreach ($snapshots as $snapshot) {
                foreach ($this->asientoIdsDesdeSnapshot($snapshot) as $asientoId) {
                    $idsSnapshot->push($asientoId);
                }
            }
        }

        $prefijoMes = sprintf('%04d-%02d-', $anio, self::MES);
        $legacyQuery = Asiento::query()
            ->where('observacion', 'like', self::PREFIJO_LEGACY.$prefijoMes.'%');

        if ($empresaId !== null && $empresaId > 0) {
            $legacyQuery->where('empresa_id', $empresaId);
        }

        $idsLegacy = $legacyQuery->pluck('id');

        return $idsSnapshot
            ->merge($idsLegacy)
            ->unique()
            ->filter(static fn ($id) => (int) $id > 0)
            ->sort()
            ->values()
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function requiereActualizacionCabecera(string $observacion): bool
    {
        return trim($observacion) !== CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO;
    }

    public function requiereActualizacionLinea(string $observacion): bool
    {
        return $this->requiereActualizacionCabecera($observacion);
    }

    public function sanitizarDescMovAnita(string $texto): string
    {
        return preg_replace('/([^A-Za-z0-9 ])/', '', $texto) ?? '';
    }

    /**
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    public function ejecutarDesdeConfig(bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion($this->asientosAfectadosDesdeConfig($empresaId), $dryRun);
    }

    /**
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    public function ejecutar(int $anio, bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion($this->asientosAfectados($anio, $empresaId), $dryRun);
    }

    /**
     * @param  Collection<int, Asiento>  $asientos
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    private function ejecutarSobreColeccion(Collection $asientos, bool $dryRun): array
    {
        $resultado = [
            'asientos_erp' => 0,
            'lineas_erp' => 0,
            'lineas_anita' => 0,
            'ya_ok' => 0,
            'errores' => [],
        ];

        foreach ($asientos as $asiento) {
            $huboCambio = false;

            if ($this->requiereActualizacionCabecera((string) ($asiento->observacion ?? ''))) {
                if (! $dryRun) {
                    $asiento->observacion = CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO;
                    $asiento->save();
                }
                $resultado['asientos_erp']++;
                $huboCambio = true;
            }

            $lineas = Asiento_Movimiento::query()
                ->where('asiento_id', $asiento->id)
                ->orderBy('id')
                ->get();

            foreach ($lineas as $linea) {
                if (! $this->requiereActualizacionLinea((string) ($linea->observacion ?? ''))) {
                    continue;
                }
                if (! $dryRun) {
                    $linea->observacion = CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO;
                    $linea->save();
                }
                $resultado['lineas_erp']++;
                $huboCambio = true;
            }

            $empresa = Empresa::query()->find($asiento->empresa_id);
            $codigoEmpresa = (string) ($empresa->codigo ?? '1');
            $nroAsiento = trim((string) ($asiento->numeroasiento ?? ''));

            if ($nroAsiento === '') {
                if ($huboCambio) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.': sin numeroasiento para ctamov.';
                }
                continue;
            }

            if ($dryRun) {
                try {
                    $pendientesAnita = $this->contarLineasAnitaPendientes($codigoEmpresa, $nroAsiento);
                    $resultado['lineas_anita'] += $pendientesAnita;
                    if (! $huboCambio && $pendientesAnita === 0) {
                        $resultado['ya_ok']++;
                    }
                } catch (RuntimeException $e) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
                }
                continue;
            }

            try {
                $actualizadas = $this->actualizarLineasAnita($codigoEmpresa, $nroAsiento);
                $resultado['lineas_anita'] += $actualizadas;
                if (! $huboCambio && $actualizadas === 0) {
                    $resultado['ya_ok']++;
                }
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
            }
        }

        return $resultado;
    }

    /**
     * @return list<int>
     */
    private function asientoIdsDesdeSnapshot(GastronomiaCierreJornadaProcesoSnapshot $snapshot): array
    {
        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? [];
        if (! is_array($asientos)) {
            return [];
        }

        $ids = [];
        foreach ($asientos as $item) {
            if (! is_array($item)) {
                continue;
            }
            $asientoId = (int) ($item['asiento_id'] ?? 0);
            if ($asientoId > 0) {
                $ids[] = $asientoId;
            }
        }

        return $ids;
    }

    private function contarLineasAnitaPendientes(string $codigoEmpresa, string $nroAsiento): int
    {
        $descNueva = $this->sanitizarDescMovAnita(
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
        );
        $pendientes = 0;

        foreach ($this->lineasCtamov($codigoEmpresa, $nroAsiento) as $fila) {
            if ((string) ($fila['ctav_desc_mov'] ?? '') !== $descNueva) {
                $pendientes++;
            }
        }

        return $pendientes;
    }

    /**
     * @return list<array{ctav_nro_linea:string,ctav_desc_mov:string}>
     */
    private function lineasCtamov(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_desc_mov',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        if ($filas === []) {
            throw new RuntimeException('No se encontraron líneas ctamov en Anita.');
        }

        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $out[] = [
                'ctav_nro_linea' => (string) ($row['ctav_nro_linea'] ?? ''),
                'ctav_desc_mov' => (string) ($row['ctav_desc_mov'] ?? ''),
            ];
        }

        return $out;
    }

    private function actualizarLineasAnita(string $codigoEmpresa, string $nroAsiento): int
    {
        $descNueva = $this->sanitizarDescMovAnita(
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
        );
        $actualizadas = 0;
        $api = new ApiAnita;

        foreach ($this->lineasCtamov($codigoEmpresa, $nroAsiento) as $fila) {
            $linea = $fila['ctav_nro_linea'];
            $desc = $fila['ctav_desc_mov'];

            if ($desc === $descNueva) {
                continue;
            }

            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_desc_mov = '".$descNueva."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$linea."' ",
            ], 'ctamov update descripcion cierre jornada julio');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }
}
