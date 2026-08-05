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
 * Backfill: centro de costo 85 en líneas de asientos cierre Waitry desde una fecha — ERP + Anita ctamov.
 */
final class CorregirCentrocostoAsientoCierreJornadaSupport
{
    private const PREFIJO_LEGACY = 'Cierre Waitry jornada ';

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(string $desde, ?int $empresaId = null): Collection
    {
        $ids = $this->asientoIdsDesde($desde, $empresaId);
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
     *   desde:string,
     *   jornadas:int,
     *   jornadas_con_snapshot:int,
     *   asientos_snapshot:int,
     *   por_empresa:array<int,array{jornadas:int,asientos:int}>,
     *   por_dia_faltantes:array<string,array{lineas_sin_cc:int,asientos:int}>
     * }
     */
    public function resumenAlcance(string $desde, ?int $empresaId = null): array
    {
        $jornadaQuery = JornadaGastronomia::query()
            ->whereDate('fecha_jornada', '>=', $desde);

        if ($empresaId !== null && $empresaId > 0) {
            $jornadaQuery->where('empresa_id', $empresaId);
        }

        $jornadas = $jornadaQuery->get(['id', 'empresa_id', 'fecha_jornada']);
        $jornadaIds = $jornadas->pluck('id');

        $porEmpresa = [];
        $asientoIds = [];

        if ($jornadaIds->isNotEmpty()) {
            $snapshots = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->whereIn('jornada_gastronomia_id', $jornadaIds)
                ->get(['jornada_gastronomia_id', 'payload']);

            $empresaPorJornada = $jornadas->keyBy('id')->map(
                static fn (JornadaGastronomia $j) => (int) $j->empresa_id,
            );

            foreach ($snapshots as $snapshot) {
                $jid = (int) $snapshot->jornada_gastronomia_id;
                $ids = $this->asientoIdsDesdeSnapshot($snapshot);
                $cant = count($ids);
                foreach ($ids as $asientoId) {
                    $asientoIds[$asientoId] = true;
                }

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

        ksort($porEmpresa);

        return [
            'desde' => $desde,
            'jornadas' => $jornadas->count(),
            'jornadas_con_snapshot' => $jornadaIds->isEmpty() ? 0 : GastronomiaCierreJornadaProcesoSnapshot::query()
                ->whereIn('jornada_gastronomia_id', $jornadaIds)
                ->count(),
            'asientos_snapshot' => count($asientoIds),
            'por_empresa' => $porEmpresa,
            'por_dia_faltantes' => $this->resumenFaltantesPorDia($desde, $empresaId),
        ];
    }

    /**
     * @return array<string, array{lineas_sin_cc:int, asientos:int}>
     */
    public function resumenFaltantesPorDia(string $desde, ?int $empresaId = null): array
    {
        $ccObjetivo = CierreJornadaProcesoAsientosCentrocostoSupport::idCentrocostoGastronomia();
        if ($ccObjetivo === null) {
            return [];
        }

        $asientos = $this->asientosAfectados($desde, $empresaId);
        if ($asientos->isEmpty()) {
            return [];
        }

        $asientoToFecha = $this->mapaAsientoFechaJornada($desde, $empresaId);
        $porDia = [];

        foreach ($asientos->chunk(100) as $chunk) {
            $ids = $chunk->pluck('id')->all();
            $lineas = Asiento_Movimiento::query()
                ->whereIn('asiento_id', $ids)
                ->get(['asiento_id', 'cuentacontable_id', 'centrocosto_id']);

            foreach ($lineas as $linea) {
                if (! CierreJornadaProcesoAsientosCentrocostoSupport::cuentacontableManejaCentroCosto(
                    (int) ($linea->cuentacontable_id ?? 0),
                )) {
                    continue;
                }
                if ((int) ($linea->centrocosto_id ?? 0) === $ccObjetivo) {
                    continue;
                }

                $aid = (int) $linea->asiento_id;
                $fecha = $asientoToFecha[$aid] ?? (string) ($chunk->firstWhere('id', $aid)?->fecha?->format('Y-m-d') ?? 'sin_fecha');
                if (! isset($porDia[$fecha])) {
                    $porDia[$fecha] = ['lineas_sin_cc' => 0, 'asientos' => []];
                }
                $porDia[$fecha]['lineas_sin_cc']++;
                $porDia[$fecha]['asientos'][$aid] = true;
            }
        }

        $out = [];
        foreach ($porDia as $fecha => $datos) {
            $out[$fecha] = [
                'lineas_sin_cc' => $datos['lineas_sin_cc'],
                'asientos' => count($datos['asientos']),
            ];
        }
        ksort($out);

        return $out;
    }

    /**
     * @return list<int>
     */
    public function asientoIdsDesde(string $desde, ?int $empresaId = null): array
    {
        $jornadaQuery = JornadaGastronomia::query()
            ->whereDate('fecha_jornada', '>=', $desde);

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

        $legacyQuery = Asiento::query()
            ->where('observacion', 'like', self::PREFIJO_LEGACY.'%')
            ->whereDate('fecha', '>=', $desde);

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
    public function ejecutar(string $desde, bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion($this->asientosAfectados($desde, $empresaId), $dryRun);
    }

    /**
     * Solo asientos con alguna línea que aún requiere CC (más rápido para backfill puntual).
     *
     * @return array{
     *   asientos_erp:int,
     *   lineas_erp:int,
     *   lineas_anita:int,
     *   ya_ok:int,
     *   errores:list<string>
     * }
     */
    public function ejecutarSoloPendientes(string $desde, bool $dryRun = false, ?int $empresaId = null): array
    {
        CierreJornadaProcesoAsientosCentrocostoSupport::idCentrocostoGastronomiaOError();
        $asientos = $this->asientosAfectados($desde, $empresaId);
        $pendientes = collect();

        foreach ($asientos->chunk(100) as $chunk) {
            $ids = $chunk->pluck('id')->all();
            $lineas = Asiento_Movimiento::query()
                ->whereIn('asiento_id', $ids)
                ->get(['asiento_id', 'cuentacontable_id', 'centrocosto_id']);

            $asientosConFalta = [];
            foreach ($lineas as $linea) {
                if ($this->requiereActualizacionLinea($linea)) {
                    $asientosConFalta[(int) $linea->asiento_id] = true;
                }
            }

            if ($asientosConFalta !== []) {
                $pendientes = $pendientes->merge(
                    $chunk->whereIn('id', array_keys($asientosConFalta)),
                );
            }
        }

        return $this->ejecutarSobreColeccion($pendientes->unique('id')->values(), $dryRun);
    }

    /**
     * @return array<int, string> asiento_id => Y-m-d
     */
    private function mapaAsientoFechaJornada(string $desde, ?int $empresaId = null): array
    {
        $jornadaQuery = JornadaGastronomia::query()
            ->whereDate('fecha_jornada', '>=', $desde);

        if ($empresaId !== null && $empresaId > 0) {
            $jornadaQuery->where('empresa_id', $empresaId);
        }

        $jornadas = $jornadaQuery->get(['id', 'fecha_jornada']);
        if ($jornadas->isEmpty()) {
            return [];
        }

        $fechaPorJornada = [];
        foreach ($jornadas as $jornada) {
            $fechaPorJornada[(int) $jornada->id] = $jornada->fecha_jornada->format('Y-m-d');
        }

        $snapshots = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->whereIn('jornada_gastronomia_id', $jornadas->pluck('id'))
            ->get(['jornada_gastronomia_id', 'payload']);

        $map = [];
        foreach ($snapshots as $snapshot) {
            $fecha = $fechaPorJornada[(int) $snapshot->jornada_gastronomia_id] ?? null;
            if ($fecha === null) {
                continue;
            }
            foreach ($this->asientoIdsDesdeSnapshot($snapshot) as $asientoId) {
                $map[$asientoId] = $fecha;
            }
        }

        return $map;
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
            ], 'ctamov update ccosto cierre jornada waitry');

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
