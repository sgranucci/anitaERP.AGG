<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Backfill: centro de costo en asientos del cierre bingo (ERP + Anita ctamov).
 * Paridad CCOSV_asigna_ccosto / p-vtabingo.c vía CierreRendicionBingoCentrocostoSupport.
 */
final class CorregirCentrocostoAsientoCierreBingoSupport
{
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
     *   desde: string,
     *   rendiciones_cerradas: int,
     *   asientos: int,
     *   por_empresa: array<int, array{rendiciones: int, asientos: int}>
     * }
     */
    public function resumenAlcance(string $desde, ?int $empresaId = null): array
    {
        $rendiciones = $this->queryRendicionesCerradas($desde, $empresaId)->get();
        $asientoIds = collect($this->asientoIdsDesde($desde, $empresaId));

        $porEmpresa = [];
        foreach ($rendiciones as $rendicion) {
            $emp = (int) $rendicion->empresa_id;
            if ($emp <= 0) {
                continue;
            }
            if (! isset($porEmpresa[$emp])) {
                $porEmpresa[$emp] = ['rendiciones' => 0, 'asientos' => []];
            }
            $porEmpresa[$emp]['rendiciones']++;
            foreach ($this->idsAsientoDeRendicion($rendicion) as $aid) {
                $porEmpresa[$emp]['asientos'][$aid] = true;
            }
        }

        $porEmpresaOut = [];
        foreach ($porEmpresa as $emp => $datos) {
            $porEmpresaOut[$emp] = [
                'rendiciones' => $datos['rendiciones'],
                'asientos' => count($datos['asientos']),
            ];
        }
        ksort($porEmpresaOut);

        return [
            'desde' => $desde,
            'rendiciones_cerradas' => $rendiciones->count(),
            'asientos' => $asientoIds->count(),
            'por_empresa' => $porEmpresaOut,
        ];
    }

    /**
     * @return list<int>
     */
    public function asientoIdsDesde(string $desde, ?int $empresaId = null): array
    {
        $ids = [];
        foreach ($this->queryRendicionesCerradas($desde, $empresaId)->get() as $rendicion) {
            foreach ($this->idsAsientoDeRendicion($rendicion) as $aid) {
                $ids[$aid] = true;
            }
        }

        $obsQuery = Asiento::query()
            ->whereDate('fecha', '>=', $desde)
            ->where(function ($q) {
                $q->where('observacion', 'like', CierreRendicionBingoAsientoSupport::DESCRIPCION_ASIENTO.' — %')
                    ->orWhere('observacion', CierreRendicionBingoAsientoSupport::LEYENDA_PAGO_PREMIOS)
                    ->orWhere('observacion', CierreRendicionBingoAsientoSupport::LEYENDA_DEV_POZO)
                    ->orWhere('observacion', CierreRendicionBingoAsientoSupport::LEYENDA_CANON_HOSPITAL)
                    ->orWhere('observacion', 'like', 'Canon %');
            });

        if ($empresaId !== null && $empresaId > 0) {
            $obsQuery->where('empresa_id', $empresaId);
        }

        foreach ($obsQuery->pluck('id') as $id) {
            $aid = (int) $id;
            if ($aid > 0) {
                $ids[$aid] = true;
            }
        }

        $out = array_map('intval', array_keys($ids));
        sort($out);

        return $out;
    }

    public function requiereActualizacionLinea(Asiento_Movimiento $linea): bool
    {
        $ccObjetivo = CierreRendicionBingoCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
            (int) ($linea->cuentacontable_id ?? 0),
        );

        if ($ccObjetivo === null) {
            return false;
        }

        return (int) ($linea->centrocosto_id ?? 0) !== $ccObjetivo;
    }

    public function lineaLlevaCentrocosto(Asiento_Movimiento $linea): bool
    {
        return CierreRendicionBingoCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
            (int) ($linea->cuentacontable_id ?? 0),
        ) !== null;
    }

    /**
     * @return array{
     *   asientos_erp: int,
     *   lineas_erp: int,
     *   lineas_anita: int,
     *   ya_ok: int,
     *   errores: list<string>,
     *   detalle: list<string>
     * }
     */
    public function ejecutar(string $desde, bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion($this->asientosAfectados($desde, $empresaId), $dryRun);
    }

    private function queryRendicionesCerradas(string $desde, ?int $empresaId = null)
    {
        $query = RendicionBingoCaja::query()
            ->whereNotNull('asiento_id')
            ->where('asiento_id', '>', 0)
            ->where(function ($q) use ($desde) {
                $q->whereDate('cierre_contable_en', '>=', $desde)
                    ->orWhereDate('fecha_jornada', '>=', $desde);
            });

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function idsAsientoDeRendicion(RendicionBingoCaja $rendicion): array
    {
        $ids = [];
        $principal = (int) ($rendicion->asiento_id ?? 0);
        if ($principal > 0) {
            $ids[$principal] = true;
        }
        $json = is_array($rendicion->asientos_cierre_ids_json) ? $rendicion->asientos_cierre_ids_json : [];
        foreach ($json as $aid) {
            $aid = (int) $aid;
            if ($aid > 0) {
                $ids[$aid] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @param  Collection<int, Asiento>  $asientos
     * @return array{
     *   asientos_erp: int,
     *   lineas_erp: int,
     *   lineas_anita: int,
     *   ya_ok: int,
     *   errores: list<string>,
     *   detalle: list<string>
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
            'detalle' => [],
        ];

        /** @var array<int, string> $codigoCcPorId */
        $codigoCcPorId = [];

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

                $ccObjetivo = CierreRendicionBingoCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
                    (int) ($linea->cuentacontable_id ?? 0),
                );
                if ($ccObjetivo === null) {
                    continue;
                }

                $cuentaCodigo = (string) (Cuentacontable::query()
                    ->whereKey((int) $linea->cuentacontable_id)
                    ->value('codigo') ?? $linea->cuentacontable_id);
                $ccCodigo = $this->codigoCentrocosto($ccObjetivo, $codigoCcPorId);

                $resultado['detalle'][] = sprintf(
                    'Asiento %s línea #%d cuenta %s: CC %s → %s',
                    (string) ($asiento->numeroasiento ?? $asiento->id),
                    (int) $linea->id,
                    $cuentaCodigo,
                    (string) ((int) ($linea->centrocosto_id ?? 0) ?: '—'),
                    $ccCodigo,
                );

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
                $pendientesAnita = $this->contarLineasAnitaPendientes(
                    $codigoEmpresa,
                    $nroAsiento,
                    $lineas,
                    $codigoCcPorId,
                );
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
                        $codigoCcPorId,
                    );
                } catch (RuntimeException $e) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
                }
            }
        }

        return $resultado;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Asiento_Movimiento>  $lineasErp
     * @param  array<int, string>  $codigoCcPorId
     */
    private function contarLineasAnitaPendientes(
        string $codigoEmpresa,
        string $nroAsiento,
        $lineasErp,
        array &$codigoCcPorId,
    ): int {
        $lineasAnita = $this->lineasCtamovPorCuenta($codigoEmpresa, $nroAsiento);
        $pendientes = 0;

        foreach ($lineasErp as $linea) {
            if (! $this->lineaLlevaCentrocosto($linea)) {
                continue;
            }

            $ccObjetivo = CierreRendicionBingoCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
                (int) ($linea->cuentacontable_id ?? 0),
            );
            if ($ccObjetivo === null) {
                continue;
            }

            $cuentaCodigo = (string) (Cuentacontable::query()
                ->whereKey((int) $linea->cuentacontable_id)
                ->value('codigo') ?? '');
            $codigoCc = $this->codigoCentrocosto($ccObjetivo, $codigoCcPorId);
            $anita = $lineasAnita[$cuentaCodigo] ?? null;
            if ($anita === null || (string) ($anita['ctav_ccosto'] ?? '0') !== $codigoCc) {
                $pendientes++;
            }
        }

        return $pendientes;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Asiento_Movimiento>  $lineasErp
     * @param  array<int, string>  $codigoCcPorId
     */
    private function actualizarLineasAnita(
        string $codigoEmpresa,
        string $nroAsiento,
        $lineasErp,
        array &$codigoCcPorId,
    ): int {
        $lineasAnita = $this->lineasCtamovPorCuenta($codigoEmpresa, $nroAsiento);
        $actualizadas = 0;
        $api = new ApiAnita;

        foreach ($lineasErp as $linea) {
            if (! $this->lineaLlevaCentrocosto($linea)) {
                continue;
            }

            $ccObjetivo = CierreRendicionBingoCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
                (int) ($linea->cuentacontable_id ?? 0),
            );
            if ($ccObjetivo === null) {
                continue;
            }

            $cuentaCodigo = (string) (Cuentacontable::query()
                ->whereKey((int) $linea->cuentacontable_id)
                ->value('codigo') ?? '');
            $codigoCc = $this->codigoCentrocosto($ccObjetivo, $codigoCcPorId);
            $anita = $lineasAnita[$cuentaCodigo] ?? null;
            if ($anita === null) {
                throw new RuntimeException('No se encontró ctamov para cuenta '.$cuentaCodigo.'.');
            }

            if ((string) ($anita['ctav_ccosto'] ?? '0') === $codigoCc) {
                continue;
            }

            $lineaAnita = (string) ($anita['ctav_nro_linea'] ?? '');
            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_ccosto = '".$codigoCc."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$lineaAnita."' ",
            ], 'ctamov update ccosto cierre bingo');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim($respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * @return array<string, array{ctav_nro_linea: string, ctav_cuenta: string, ctav_ccosto: string}>
     */
    private function lineasCtamovPorCuenta(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            // descripción al final: regla bridge CSV
            'campos' => 'ctav_nro_linea,ctav_cuenta,ctav_ccosto,ctav_desc_mov',
            'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' AND ctav_nro_asiento = '".$nroAsiento."'",
        ]));

        if ($filas === []) {
            throw new RuntimeException('No se encontraron líneas ctamov en Anita.');
        }

        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $cuenta = (string) ((int) ($row['ctav_cuenta'] ?? 0));
            if ($cuenta === '0') {
                continue;
            }
            $out[$cuenta] = [
                'ctav_nro_linea' => (string) ($row['ctav_nro_linea'] ?? ''),
                'ctav_cuenta' => $cuenta,
                'ctav_ccosto' => (string) ($row['ctav_ccosto'] ?? '0'),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $codigoCcPorId
     */
    private function codigoCentrocosto(int $centrocostoId, array &$codigoCcPorId): string
    {
        if (! isset($codigoCcPorId[$centrocostoId])) {
            $codigoCcPorId[$centrocostoId] = (string) (Centrocosto::query()
                ->whereKey($centrocostoId)
                ->value('codigo') ?? '');
        }

        return $codigoCcPorId[$centrocostoId];
    }
}
