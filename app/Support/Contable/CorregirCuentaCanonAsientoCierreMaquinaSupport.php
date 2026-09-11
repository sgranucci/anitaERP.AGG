<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\RendicionMaquina;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Backfill: cánones de cierre máquinas imputados a 521020 (sala bingo)
 * → 521010 (máquinas). ERP + ctamov.
 */
final class CorregirCuentaCanonAsientoCierreMaquinaSupport
{
    /** @var array<string, string> codigo sala bingo → codigo máquinas */
    public const MAPA_CODIGO = [
        '521020001' => '521010001',
        '521020002' => '521010002',
    ];

    /**
     * @return Collection<int, Asiento>
     */
    public function asientosAfectados(?int $empresaId = null): Collection
    {
        $ids = $this->asientoIdsCierreMaquina($empresaId);
        if ($ids === []) {
            return collect();
        }

        $codigosOrigen = array_keys(self::MAPA_CODIGO);
        $cuentaIds = Cuentacontable::query()
            ->whereIn('codigo', $codigosOrigen)
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($cuentaIds === []) {
            return collect();
        }

        $asientoIdsConLinea = Asiento_Movimiento::query()
            ->whereIn('asiento_id', $ids)
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->distinct()
            ->pluck('asiento_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($asientoIdsConLinea === []) {
            return collect();
        }

        return Asiento::query()
            ->whereIn('id', $asientoIdsConLinea)
            ->orderBy('empresa_id')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *   rendiciones_cerradas: int,
     *   asientos: int,
     *   por_empresa: array<int, array{rendiciones: int, asientos: int}>
     * }
     */
    public function resumenAlcance(?int $empresaId = null): array
    {
        $rendiciones = $this->queryRendicionesCerradas($empresaId)->get();
        $asientos = $this->asientosAfectados($empresaId);
        $porEmpresa = [];

        foreach ($rendiciones as $rendicion) {
            $emp = (int) $rendicion->empresa_id;
            if (! isset($porEmpresa[$emp])) {
                $porEmpresa[$emp] = ['rendiciones' => 0, 'asientos' => 0];
            }
            $porEmpresa[$emp]['rendiciones']++;
        }
        foreach ($asientos as $asiento) {
            $emp = (int) $asiento->empresa_id;
            if (! isset($porEmpresa[$emp])) {
                $porEmpresa[$emp] = ['rendiciones' => 0, 'asientos' => 0];
            }
            $porEmpresa[$emp]['asientos']++;
        }
        ksort($porEmpresa);

        return [
            'rendiciones_cerradas' => $rendiciones->count(),
            'asientos' => $asientos->count(),
            'por_empresa' => $porEmpresa,
        ];
    }

    /**
     * @return array{
     *   asientos_erp: int,
     *   lineas_erp: int,
     *   lineas_anita: int,
     *   ya_ok: int,
     *   errores: list<string>
     * }
     */
    public function ejecutar(bool $dryRun = false, ?int $empresaId = null): array
    {
        return $this->ejecutarSobreColeccion($this->asientosAfectados($empresaId), $dryRun);
    }

    /**
     * @param  Collection<int, Asiento>  $asientos
     * @return array{
     *   asientos_erp: int,
     *   lineas_erp: int,
     *   lineas_anita: int,
     *   ya_ok: int,
     *   errores: list<string>
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

        /** @var array<string, array<int, int>> $cacheCuentaPorEmpresa [codigo][empresaId] = id */
        $cacheCuentaPorEmpresa = [];

        foreach ($asientos as $asiento) {
            $empresaId = (int) $asiento->empresa_id;
            $lineas = Asiento_Movimiento::query()
                ->where('asiento_id', $asiento->id)
                ->orderBy('id')
                ->get();

            $huboCambioErp = false;
            /** @var list<array{idx: int, codigo_origen: string, codigo_destino: string}> $cambios */
            $cambios = [];

            foreach ($lineas->values() as $idx => $linea) {
                $cuentaId = (int) ($linea->cuentacontable_id ?? 0);
                $codigoActual = $this->codigoCuenta($cuentaId);
                $codigoDestino = self::MAPA_CODIGO[$codigoActual] ?? null;
                if ($codigoDestino === null) {
                    continue;
                }

                $cuentaNuevaId = $this->resolverCuentaIdPorEmpresa(
                    $empresaId,
                    $codigoDestino,
                    $cacheCuentaPorEmpresa,
                );
                if ($cuentaNuevaId <= 0) {
                    $resultado['errores'][] = 'Asiento ERP #'.$asiento->id
                        .': no existe cuenta '.$codigoDestino.' para empresa '.$empresaId.'.';

                    continue;
                }

                if (! $dryRun) {
                    $linea->cuentacontable_id = $cuentaNuevaId;
                    $linea->save();
                }

                $resultado['lineas_erp']++;
                $huboCambioErp = true;
                $cambios[] = [
                    'idx' => $idx,
                    'codigo_origen' => $codigoActual,
                    'codigo_destino' => $codigoDestino,
                ];
            }

            if ($huboCambioErp) {
                $resultado['asientos_erp']++;
            } else {
                $resultado['ya_ok']++;
            }

            if ($cambios === []) {
                continue;
            }

            $empresa = Empresa::query()->find($asiento->empresa_id);
            $codigoEmpresa = (string) ($empresa->codigo ?? '1');
            $nroAsiento = trim((string) ($asiento->numeroasiento ?? ''));

            if ($nroAsiento === '') {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.': sin numeroasiento para ctamov.';

                continue;
            }

            if ($dryRun) {
                $resultado['lineas_anita'] += count($cambios);

                continue;
            }

            try {
                $resultado['lineas_anita'] += $this->actualizarLineasAnita(
                    $codigoEmpresa,
                    $nroAsiento,
                    $cambios,
                );
            } catch (RuntimeException $e) {
                $resultado['errores'][] = 'Asiento ERP #'.$asiento->id.' (Anita '.$nroAsiento.'): '.$e->getMessage();
            }
        }

        return $resultado;
    }

    /**
     * @return list<int>
     */
    private function asientoIdsCierreMaquina(?int $empresaId): array
    {
        $ids = [];
        foreach ($this->queryRendicionesCerradas($empresaId)->get() as $rendicion) {
            $jsonIds = is_array($rendicion->asientos_cierre_ids_json)
                ? $rendicion->asientos_cierre_ids_json
                : [];
            foreach ($jsonIds as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
            $principal = (int) ($rendicion->asiento_id ?? 0);
            if ($principal > 0) {
                $ids[$principal] = $principal;
            }
        }

        return array_values($ids);
    }

    private function queryRendicionesCerradas(?int $empresaId)
    {
        $query = RendicionMaquina::query()
            ->whereNotNull('cierre_contable_en')
            ->where(function ($q) {
                $q->whereNotNull('asientos_cierre_ids_json')
                    ->orWhere('asiento_id', '>', 0);
            });
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    /**
     * @param  array<string, array<int, int>>  $cache
     */
    private function resolverCuentaIdPorEmpresa(int $empresaId, string $codigo, array &$cache): int
    {
        if (isset($cache[$codigo][$empresaId])) {
            return $cache[$codigo][$empresaId];
        }

        $id = (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
        $cache[$codigo][$empresaId] = $id;

        return $id;
    }

    private function codigoCuenta(int $cuentacontableId): string
    {
        if ($cuentacontableId <= 0) {
            return '';
        }

        return (string) (Cuentacontable::query()->whereKey($cuentacontableId)->value('codigo') ?? '');
    }

    /**
     * @param  list<array{idx: int, codigo_origen: string, codigo_destino: string}>  $cambios
     */
    private function actualizarLineasAnita(string $codigoEmpresa, string $nroAsiento, array $cambios): int
    {
        $lineasAnita = $this->lineasCtamov($codigoEmpresa, $nroAsiento);
        $actualizadas = 0;
        $api = new ApiAnita;

        foreach ($cambios as $cambio) {
            $anita = $lineasAnita[$cambio['idx']] ?? null;
            if ($anita === null) {
                throw new RuntimeException('No se encontró línea ctamov #'.$cambio['idx'].' para el asiento.');
            }

            $cuentaActual = (string) ((int) ($anita['ctav_cuenta'] ?? 0));
            if ($cuentaActual === $cambio['codigo_destino']) {
                continue;
            }
            if ($cuentaActual !== $cambio['codigo_origen']) {
                throw new RuntimeException(
                    'Línea ctamov #'.$cambio['idx'].' tiene cuenta '.$cuentaActual
                    .'; se esperaba '.$cambio['codigo_origen'].'.',
                );
            }

            $lineaAnita = (string) ($anita['ctav_nro_linea'] ?? '');
            $respuesta = $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'ctamov',
                'sistema' => 'contab',
                'valores' => " ctav_cuenta = '".$cambio['codigo_destino']."' ",
                'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
                    ."' AND ctav_nro_asiento = '".$nroAsiento
                    ."' AND ctav_nro_linea = '".$lineaAnita."' ",
            ], 'ctamov update cuenta canon cierre maquina');

            if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
                $err = ApiAnita::extraerMensajeError($respuesta) ?? trim((string) $respuesta);
                throw new RuntimeException('Bridge Anita: '.($err !== '' ? $err : 'respuesta no exitosa'));
            }

            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * @return array<int, array{ctav_nro_linea: string, ctav_cuenta: string}>
     */
    private function lineasCtamov(string $codigoEmpresa, string $nroAsiento): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'tabla' => 'ctamov',
            'sistema' => 'contab',
            'campos' => 'ctav_nro_linea,ctav_cuenta',
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
                'ctav_cuenta' => (string) ((int) ($row['ctav_cuenta'] ?? 0)),
            ];
        }

        return $out;
    }
}
