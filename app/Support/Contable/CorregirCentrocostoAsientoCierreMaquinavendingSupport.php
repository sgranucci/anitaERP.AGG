<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Backfill: centro de costo en asientos del cierre contable vending (ERP + Anita ctamov).
 */
final class CorregirCentrocostoAsientoCierreMaquinavendingSupport
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
     *   desde:string,
     *   rendiciones_cerradas:int,
     *   asientos:int,
     *   por_empresa:array<int,array{rendiciones:int,asientos:int}>
     * }
     */
    public function resumenAlcance(string $desde, ?int $empresaId = null): array
    {
        $rendiciones = $this->queryRendicionesCerradas($desde, $empresaId)->get();
        $asientoIds = $rendiciones
            ->pluck('asiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

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
            $aid = (int) ($rendicion->asiento_id ?? 0);
            if ($aid > 0) {
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
        $idsRendicion = $this->queryRendicionesCerradas($desde, $empresaId)
            ->pluck('asiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0);

        $obsQuery = Asiento::query()
            ->where('observacion', 'like', CierreRendicionMaquinavendingAsientoSupport::DESCRIPCION_ASIENTO.' — %')
            ->whereDate('fecha', '>=', $desde);

        if ($empresaId !== null && $empresaId > 0) {
            $obsQuery->where('empresa_id', $empresaId);
        }

        return $idsRendicion
            ->merge($obsQuery->pluck('id'))
            ->unique()
            ->filter(static fn ($id) => (int) $id > 0)
            ->sort()
            ->values()
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function requiereActualizacionLinea(Asiento_Movimiento $linea): bool
    {
        $ccObjetivo = CierreRendicionMaquinavendingCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
            (int) ($linea->cuentacontable_id ?? 0),
        );

        if ($ccObjetivo === null) {
            return false;
        }

        return (int) ($linea->centrocosto_id ?? 0) !== $ccObjetivo;
    }

    public function lineaLlevaCentrocosto(Asiento_Movimiento $linea): bool
    {
        return CierreRendicionMaquinavendingCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
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

    private function queryRendicionesCerradas(string $desde, ?int $empresaId = null)
    {
        $query = RendicionMaquinavendingCaja::query()
            ->whereNotNull('asiento_id')
            ->where('asiento_id', '>', 0)
            ->where(function ($q) use ($desde) {
                $q->whereDate('cierre_contable_en', '>=', $desde)
                    ->orWhereDate('fecharendicion', '>=', $desde);
            });

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
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

        $codigoCc = CierreRendicionMaquinavendingCentrocostoSupport::codigoCentrocostoConfigurado();

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

                $ccObjetivo = CierreRendicionMaquinavendingCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, Asiento_Movimiento>  $lineasErp
     */
    private function contarLineasAnitaPendientes(
        string $codigoEmpresa,
        string $nroAsiento,
        $lineasErp,
    ): int {
        $codigoCc = CierreRendicionMaquinavendingCentrocostoSupport::codigoCentrocostoConfigurado();
        $lineasAnita = $this->lineasCtamov($codigoEmpresa, $nroAsiento);
        $pendientes = 0;

        foreach ($lineasErp->values() as $idx => $linea) {
            if (! $this->lineaLlevaCentrocosto($linea)) {
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
            if (! $this->lineaLlevaCentrocosto($linea)) {
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
            ], 'ctamov update ccosto cierre vending');

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
