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
 * Backfill: centro de costo 85 en líneas de asientos cierre Waitry (junio) — ERP + Anita ctamov.
 */
final class CorregirCentrocostoAsientoCierreJornadaJunioSupport
{
    public const MES = 6;

    private const PREFIJO_LEGACY = 'Cierre Waitry jornada ';

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(int $anio, ?int $empresaId = null): Collection
    {
        $ids = $this->asientoIdsJunio($anio, $empresaId);
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
     * @return array{
     *   jornadas_mes:int,
     *   jornadas_con_snapshot:int,
     *   asientos_snapshot:int,
     *   promedio_asientos_por_jornada:float,
     *   distribucion_por_cantidad_asientos:array<int,int>,
     *   por_empresa:array<int,array{jornadas:int,asientos:int}>
     * }
     */
    public function resumenAlcance(int $anio, ?int $empresaId = null): array
    {
        $jornadaQuery = JornadaGastronomia::query()
            ->whereYear('fecha_jornada', $anio)
            ->whereMonth('fecha_jornada', self::MES);

        if ($empresaId !== null && $empresaId > 0) {
            $jornadaQuery->where('empresa_id', $empresaId);
        }

        $jornadas = $jornadaQuery->get(['id', 'empresa_id']);
        $jornadaIds = $jornadas->pluck('id');

        $porJornada = [];
        $porEmpresa = [];
        $distribucion = [];

        if ($jornadaIds->isNotEmpty()) {
            $snapshots = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->whereIn('jornada_gastronomia_id', $jornadaIds)
                ->get(['jornada_gastronomia_id', 'payload']);

            $empresaPorJornada = $jornadas->keyBy('id')->map(
                static fn (JornadaGastronomia $j) => (int) $j->empresa_id,
            );

            foreach ($snapshots as $snapshot) {
                $jid = (int) $snapshot->jornada_gastronomia_id;
                $cant = count($this->asientoIdsDesdeSnapshot($snapshot));
                $porJornada[$jid] = $cant;
                $distribucion[$cant] = ($distribucion[$cant] ?? 0) + 1;

                $emp = (int) ($empresaPorJornada[$jid] ?? 0);
                if ($emp > 0) {
                    if (! isset($porEmpresa[$emp])) {
                        $porEmpresa[$emp] = ['jornadas' => 0, 'asientos' => 0];
                    }
                    $porEmpresa[$emp]['jornadas']++;
                    $porEmpresa[$emp]['asientos'] += $cant;
                }
            }
        }

        ksort($distribucion);
        ksort($porEmpresa);

        $totalAsientos = array_sum($porJornada);
        $jornadasConSnapshot = count($porJornada);

        return [
            'jornadas_mes' => $jornadas->count(),
            'jornadas_con_snapshot' => $jornadasConSnapshot,
            'asientos_snapshot' => $totalAsientos,
            'promedio_asientos_por_jornada' => $jornadasConSnapshot > 0
                ? round($totalAsientos / $jornadasConSnapshot, 2)
                : 0.,
            'distribucion_por_cantidad_asientos' => $distribucion,
            'por_empresa' => $porEmpresa,
        ];
    }

    /**
     * @return list<int>
     */
    public function asientoIdsJunio(int $anio, ?int $empresaId = null): array
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

        if ($idsSnapshot->isNotEmpty()) {
            return $idsSnapshot
                ->unique()
                ->filter(static fn ($id) => (int) $id > 0)
                ->sort()
                ->values()
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        $prefijoMes = sprintf('%04d-%02d-', $anio, self::MES);
        $legacyQuery = Asiento::query()
            ->where('observacion', 'like', self::PREFIJO_LEGACY.$prefijoMes.'%');

        if ($empresaId !== null && $empresaId > 0) {
            $legacyQuery->where('empresa_id', $empresaId);
        }

        return $legacyQuery
            ->pluck('id')
            ->unique()
            ->filter(static fn ($id) => (int) $id > 0)
            ->sort()
            ->values()
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function requiereActualizacionLinea(Asiento_Movimiento $linea): bool
    {
        $ccObjetivo = CierreJornadaProcesoAsientosCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
            (int) ($linea->cuentacontable_id ?? 0),
        );

        if ($ccObjetivo === null) {
            return false;
        }

        return (int) ($linea->centrocosto_id ?? 0) !== $ccObjetivo;
    }

    public function lineaLlevaCentrocostoGastronomia(Asiento_Movimiento $linea): bool
    {
        return CierreJornadaProcesoAsientosCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
            (int) ($linea->cuentacontable_id ?? 0),
        ) !== null;
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

        $codigoCc = CierreJornadaProcesoAsientosCentrocostoSupport::codigoCentrocostoConfigurado();

        foreach ($asientos as $asiento) {
            $lineas = Asiento_Movimiento::query()
                ->where('asiento_id', $asiento->id)
                ->orderBy('id')
                ->get();

            $huboCambioErp = false;

            foreach ($lineas as $linea) {
                if (! $this->requiereActualizacionLinea($linea)) {
                    continue;
                }

                $ccObjetivo = CierreJornadaProcesoAsientosCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
                    (int) ($linea->cuentacontable_id ?? 0),
                );
                if ($ccObjetivo === null) {
                    continue;
                }

                if (! $dryRun) {
                    $linea->centrocosto_id = $ccObjetivo;
                    $linea->save();
                }

                $resultado['lineas_erp']++;
                $huboCambioErp = true;
            }

            if ($huboCambioErp) {
                $resultado['asientos_erp']++;
            }

            $empresa = Empresa::query()->find($asiento->empresa_id);
            $codigoEmpresa = (string) ($empresa->codigo ?? '1');
            $nroAsiento = trim((string) ($asiento->numeroasiento ?? ''));

            if ($nroAsiento === '') {
                if ($huboCambioErp) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.': sin numeroasiento para ctamov.';
                }
                continue;
            }

            $pendientesAnita = 0;
            try {
                $pendientesAnita = $this->contarLineasAnitaPendientes($codigoEmpresa, $nroAsiento, $lineas);
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
                continue;
            }

            if (! $huboCambioErp && $pendientesAnita === 0) {
                $resultado['ya_ok']++;
                continue;
            }

            if ($dryRun) {
                $resultado['lineas_anita'] += $pendientesAnita;
                continue;
            }

            if ($pendientesAnita > 0) {
                try {
                    $resultado['lineas_anita'] += $this->actualizarLineasAnita(
                        $codigoEmpresa,
                        $nroAsiento,
                        $lineas,
                        $codigoCc,
                    );
                } catch (RuntimeException $e) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
                }
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

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Asiento_Movimiento>  $lineasErp
     */
    private function contarLineasAnitaPendientes(
        string $codigoEmpresa,
        string $nroAsiento,
        $lineasErp,
    ): int {
        $codigoCc = CierreJornadaProcesoAsientosCentrocostoSupport::codigoCentrocostoConfigurado();
        $lineasAnita = $this->lineasCtamov($codigoEmpresa, $nroAsiento);
        $pendientes = 0;

        foreach ($lineasErp->values() as $idx => $linea) {
            if (! $this->lineaLlevaCentrocostoGastronomia($linea)) {
                continue;
            }

            $anita = $lineasAnita[$idx] ?? null;
            if ($anita === null) {
                $pendientes++;
                continue;
            }

            if ((string) ($anita['ctav_ccosto'] ?? '0') !== $codigoCc) {
                $pendientes++;
            }
        }

        return $pendientes;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Asiento_Movimiento>  $lineasErp
     */
    private function actualizarLineasAnita(
        string $codigoEmpresa,
        string $nroAsiento,
        $lineasErp,
        string $codigoCc,
    ): int {
        $lineasAnita = $this->lineasCtamov($codigoEmpresa, $nroAsiento);
        $actualizadas = 0;
        $api = new ApiAnita;

        foreach ($lineasErp->values() as $idx => $linea) {
            if (! $this->lineaLlevaCentrocostoGastronomia($linea)) {
                continue;
            }

            $anita = $lineasAnita[$idx] ?? null;
            if ($anita === null) {
                throw new RuntimeException('No se encontró línea ctamov #'.$idx.' para el asiento.');
            }

            $lineaAnita = (string) ($anita['ctav_nro_linea'] ?? '');
            $ccActual = (string) ($anita['ctav_ccosto'] ?? '0');

            if ($ccActual === $codigoCc) {
                continue;
            }

            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_ccosto = '".$codigoCc."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$lineaAnita."' ",
            ], 'ctamov update ccosto cierre jornada junio');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * @return array<int, array{ctav_nro_linea:string,ctav_cuenta:string,ctav_ccosto:string}>
     */
    private function lineasCtamov(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_cuenta,ctav_ccosto',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        if ($filas === []) {
            throw new RuntimeException('No se encontraron líneas ctamov en Anita.');
        }

        usort($filas, static function ($a, $b): int {
            $a = is_array($a) ? $a : (array) $a;
            $b = is_array($b) ? $b : (array) $b;

            return ((int) ($a['ctav_nro_linea'] ?? 0)) <=> ((int) ($b['ctav_nro_linea'] ?? 0));
        });

        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $out[] = [
                'ctav_nro_linea' => (string) ($row['ctav_nro_linea'] ?? ''),
                'ctav_cuenta' => (string) ($row['ctav_cuenta'] ?? ''),
                'ctav_ccosto' => (string) ($row['ctav_ccosto'] ?? '0'),
            ];
        }

        return $out;
    }
}
