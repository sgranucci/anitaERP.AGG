<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\ApiAnita;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Completa asiento.anita_nro_interno desde subdiario/subhist (subd_nro_interno)
 * y engancha el comprobante de proveedor con el mismo com_nro_interno.
 */
final class AsientoAnitaNroInternoBackfillService
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(int $anio, int $empresaAnita, bool $persistir): array
    {
        if (! Schema::hasColumn('asiento', 'anita_nro_interno')) {
            throw new RuntimeException(
                'Falta asiento.anita_nro_interno; ejecute primero la migración correspondiente.',
            );
        }
        if ($anio < 2000 || $anio > 2100 || $empresaAnita <= 0) {
            throw new RuntimeException('Año o empresa Anita inválidos.');
        }

        $empresaErpId = (int) (DB::table('empresa')->where('codigo', (string) $empresaAnita)->value('id') ?? 0);
        if ($empresaErpId <= 0) {
            $empresaErpId = (int) (DB::table('empresa')->where('codigo', $empresaAnita)->value('id') ?? 0);
        }
        if ($empresaErpId <= 0) {
            throw new RuntimeException("No existe empresa ERP con código Anita {$empresaAnita}.");
        }

        $porOperacion = [];
        $ambiguas = [];
        $filas = 0;
        $lecturas = 0;

        for ($mes = 1; $mes <= 12; $mes++) {
            fwrite(STDOUT, sprintf("  leyendo %04d-%02d\n", $anio, $mes));
            fflush(STDOUT);
            $desde = (int) sprintf('%04d%02d01', $anio, $mes);
            $hasta = (int) sprintf('%04d%02d%02d', $anio, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $anio));
            foreach (['subdiario' => 'subd', 'subhist' => 'subh'] as $tabla => $prefijo) {
                $lecturas++;
                $lote = $this->leerMes($tabla, $prefijo, $empresaAnita, $desde, $hasta);
                $filas += count($lote);
                foreach ($lote as $fila) {
                    $operacion = (int) ($fila->nro_operacion ?? 0);
                    $interno = (int) ($fila->nro_interno ?? 0);
                    if ($operacion <= 0 || $interno <= 0) {
                        continue;
                    }
                    $porOperacion[$operacion][$interno] = true;
                }
            }
        }

        $internoPorOperacion = [];
        foreach ($porOperacion as $operacion => $internos) {
            if (count($internos) !== 1) {
                $ambiguas[$operacion] = array_keys($internos);

                continue;
            }
            $internoPorOperacion[$operacion] = (int) array_key_first($internos);
        }

        $resultado = [
            'anio' => $anio,
            'empresa_anita' => $empresaAnita,
            'empresa_erp_id' => $empresaErpId,
            'persistir' => $persistir,
            'lecturas' => $lecturas,
            'filas' => $filas,
            'operaciones' => count($internoPorOperacion),
            'operaciones_ambiguas' => count($ambiguas),
            'asientos_actualizados' => 0,
            'asientos_ya_completos' => 0,
            'asientos_conflicto' => 0,
            'asientos_sin_match' => 0,
            'facturas_vinculadas' => 0,
            'facturas_ambiguas' => 0,
            'facturas_sin_asiento' => 0,
        ];

        /** @var array<int, int> $asientoIdPorInterno asiento_id => interno que queda */
        $asientoIdPorInterno = [];

        foreach (array_chunk($internoPorOperacion, 500, true) as $chunk) {
            $asientos = DB::table('asiento')
                ->where('empresa_id', $empresaErpId)
                ->whereIn('numeroasiento', array_keys($chunk))
                ->get(['id', 'numeroasiento', 'anita_nro_interno', 'comprobante_proveedor_id']);

            $porNumero = [];
            foreach ($asientos as $asiento) {
                $porNumero[(int) $asiento->numeroasiento][] = $asiento;
            }

            $updates = [];
            foreach ($chunk as $operacion => $interno) {
                $hits = $porNumero[$operacion] ?? [];
                if (count($hits) !== 1) {
                    $resultado['asientos_sin_match']++;

                    continue;
                }
                $asientoId = (int) $hits[0]->id;
                $actual = (int) ($hits[0]->anita_nro_interno ?? 0);
                if ($actual === $interno) {
                    $resultado['asientos_ya_completos']++;
                    $asientoIdPorInterno[$asientoId] = $interno;

                    continue;
                }
                if ($actual > 0) {
                    $resultado['asientos_conflicto']++;
                    $asientoIdPorInterno[$asientoId] = $actual;

                    continue;
                }
                $updates[] = [
                    'id' => $asientoId,
                    'anita_nro_interno' => $interno,
                ];
                $asientoIdPorInterno[$asientoId] = $interno;
            }

            if ($persistir && $updates !== []) {
                DB::table('asiento')->upsert($updates, ['id'], ['anita_nro_interno']);
            }
            $resultado['asientos_actualizados'] += count($updates);
        }

        $vinculo = $this->vincularFacturas($empresaErpId, $anio, $asientoIdPorInterno, $persistir);
        $resultado['facturas_vinculadas'] = $vinculo['vinculadas'];
        $resultado['facturas_ambiguas'] = $vinculo['ambiguas'];
        $resultado['facturas_sin_asiento'] = $vinculo['sin_asiento'];

        return $resultado;
    }

    /**
     * Engancha la factura cuando hay un solo comprobante y un solo asiento
     * con el mismo interno. No pisa un vínculo ya cargado.
     *
     * @param  array<int, int>  $asientoIdPorInterno  asiento_id => interno
     * @return array{vinculadas: int, ambiguas: int, sin_asiento: int}
     */
    private function vincularFacturas(int $empresaErpId, int $anio, array $asientoIdPorInterno, bool $persistir): array
    {
        $out = ['vinculadas' => 0, 'ambiguas' => 0, 'sin_asiento' => 0];
        $cps = DB::table('comprobante_proveedor')
            ->where('empresa_id', $empresaErpId)
            ->whereNull('asiento_id')
            ->where('anita_nro_interno', '>', 0)
            ->whereBetween('fechacomprobante', [$anio.'-01-01', $anio.'-12-31'])
            ->get(['id', 'anita_nro_interno']);

        $facturasPorInterno = [];
        foreach ($cps as $cp) {
            $facturasPorInterno[(int) $cp->anita_nro_interno][] = (int) $cp->id;
        }
        if ($facturasPorInterno === []) {
            return $out;
        }

        $asientosPorInterno = [];
        foreach ($asientoIdPorInterno as $asientoId => $interno) {
            $asientosPorInterno[(int) $interno][(int) $asientoId] = true;
        }

        $ids = array_keys($asientoIdPorInterno);
        $ocupado = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table('asiento')->whereIn('id', $chunk)->get(['id', 'comprobante_proveedor_id']) as $row) {
                $ocupado[(int) $row->id] = (int) ($row->comprobante_proveedor_id ?? 0);
            }
        }

        foreach ($facturasPorInterno as $interno => $facturas) {
            $asientoIds = array_keys($asientosPorInterno[$interno] ?? []);
            if ($asientoIds === []) {
                $out['sin_asiento'] += count($facturas);

                continue;
            }
            if (count($facturas) !== 1 || count($asientoIds) !== 1) {
                $out['ambiguas'] += count($facturas);

                continue;
            }
            $asientoId = $asientoIds[0];
            $cpId = $facturas[0];
            $ya = $ocupado[$asientoId] ?? 0;
            if ($ya > 0 && $ya !== $cpId) {
                $out['ambiguas']++;

                continue;
            }
            if (! $persistir) {
                $out['vinculadas']++;

                continue;
            }
            $nAsiento = DB::table('asiento')
                ->where('id', $asientoId)
                ->where(function ($q) use ($cpId) {
                    $q->whereNull('comprobante_proveedor_id')
                        ->orWhere('comprobante_proveedor_id', $cpId);
                })
                ->update([
                    'comprobante_proveedor_id' => $cpId,
                    'updated_at' => now(),
                ]);
            $nCp = DB::table('comprobante_proveedor')
                ->where('id', $cpId)
                ->whereNull('asiento_id')
                ->update([
                    'asiento_id' => $asientoId,
                    'updated_at' => now(),
                ]);
            if ($nAsiento > 0 && $nCp > 0) {
                $out['vinculadas']++;
            }
        }

        return $out;
    }

    /**
     * @return list<object{nro_operacion: int, nro_interno: int}>
     */
    private function leerMes(string $tabla, string $prefijo, int $empresaAnita, int $desde, int $hasta): array
    {
        $reducido = EntornoEmpresaSupport::esCrown()
            || EntornoEmpresaSupport::esFerli()
            || EntornoEmpresaSupport::esElBierzo();

        $campos = $reducido
            ? $prefijo.'_fecha,'.$prefijo.'_nro_operacion,'.$prefijo.'_nro_interno'
            : $prefijo.'_empresa,'.$prefijo.'_fecha,'.$prefijo.'_nro_operacion,'.$prefijo.'_nro_interno';
        $where = $reducido
            ? ' WHERE '.$prefijo.'_fecha BETWEEN '.$desde.' AND '.$hasta
            : ' WHERE '.$prefijo.'_empresa='.$empresaAnita
                .' AND '.$prefijo.'_fecha BETWEEN '.$desde.' AND '.$hasta;

        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => $prefijo.'_fecha, '.$prefijo.'_nro_operacion',
            'curl_timeout' => 300,
        ]);
        $error = ApiAnita::extraerMensajeError($raw);
        if ($error !== null) {
            throw new RuntimeException("No se pudo leer {$tabla} {$desde}-{$hasta}: {$error}");
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        $out = [];
        $colOp = $prefijo.'_nro_operacion';
        $colInt = $prefijo.'_nro_interno';
        foreach ($filas as $fila) {
            $out[] = (object) [
                'nro_operacion' => (int) ($fila->{$colOp} ?? 0),
                'nro_interno' => (int) ($fila->{$colInt} ?? 0),
            ];
        }

        return $out;
    }
}
