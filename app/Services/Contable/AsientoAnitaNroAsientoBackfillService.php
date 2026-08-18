<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\ApiAnita;
use App\Support\Contable\AsientoAnitaMetadatosSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AsientoAnitaNroAsientoBackfillService
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {}

    /**
     * Lee subhist una única vez para toda la empresa y año.
     *
     * @return array<string, mixed>
     */
    public function ejecutar(int $anio, int $empresaAnita, bool $persistir): array
    {
        if (! Schema::hasColumn('asiento', 'anita_nro_asiento')) {
            throw new RuntimeException(
                'Falta asiento.anita_nro_asiento; ejecute primero la migración correspondiente.',
            );
        }
        if ($anio < 2000 || $anio > 2100 || $empresaAnita <= 0) {
            throw new RuntimeException('Año o empresa Anita inválidos.');
        }

        $empresaErpId = (int) (DB::table('empresa')
            ->where('codigo', $empresaAnita)
            ->value('id') ?? 0);
        if ($empresaErpId <= 0) {
            throw new RuntimeException("No existe empresa ERP con código Anita {$empresaAnita}.");
        }

        $desdeYmd = $anio * 10000 + 101;
        $hastaYmd = $anio * 10000 + 1231;
        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'subhist',
            'campos' => 'subh_empresa,subh_fecha,subh_nro_operacion,subh_nro_asiento',
            'whereArmado' => ' WHERE subh_empresa='.$empresaAnita
                .' AND subh_fecha BETWEEN '.$desdeYmd.' AND '.$hastaYmd,
            'orderBy' => 'subh_nro_operacion',
        ]);
        $error = ApiAnita::extraerMensajeError($raw);
        if ($error !== null) {
            throw new RuntimeException('No se pudo leer subhist: '.$error);
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        $conteosPorOperacion = [];
        foreach ($filas as $fila) {
            $operacion = (int) ($fila->subh_nro_operacion ?? 0);
            $nroAsiento = (int) ($fila->subh_nro_asiento ?? 0);
            if ($operacion <= 0 || $nroAsiento <= 0) {
                continue;
            }
            $conteosPorOperacion[$operacion][$nroAsiento]
                = ($conteosPorOperacion[$operacion][$nroAsiento] ?? 0) + 1;
        }

        $porOperacion = [];
        $variantes = [];
        $ambiguas = [];
        foreach ($conteosPorOperacion as $operacion => $conteos) {
            arsort($conteos);
            $nros = array_keys($conteos);
            if (count($conteos) > 1) {
                $variantes[$operacion] = [
                    'elegido' => (int) $nros[0],
                    'apariciones' => $conteos,
                ];
            }
            if (isset($nros[1]) && $conteos[$nros[0]] === $conteos[$nros[1]]) {
                $ambiguas[$operacion] = $conteos;

                continue;
            }
            $porOperacion[$operacion] = (int) $nros[0];
        }

        $resultado = [
            'anio' => $anio,
            'empresa_anita' => $empresaAnita,
            'empresa_erp_id' => $empresaErpId,
            'persistir' => $persistir,
            'lecturas_subhist' => 1,
            'subhist_filas' => count($filas),
            'operaciones_con_nro_asiento' => count($porOperacion),
            'operaciones_con_variantes' => $variantes,
            'operaciones_ambiguas' => $ambiguas,
            'asientos_revisados' => 0,
            'asientos_con_match' => 0,
            'asientos_ya_completos' => 0,
            'asientos_actualizados' => 0,
            'asientos_sin_match' => 0,
            'muestra_sin_match' => [],
        ];

        DB::table('asiento')
            ->where('empresa_id', $empresaErpId)
            ->whereBetween('fecha', [$anio.'-01-01', $anio.'-12-31'])
            ->where('anita_origen', AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST)
            ->whereNull('deleted_at')
            ->select(['id', 'numeroasiento', 'anita_nro_asiento'])
            ->orderBy('id')
            ->chunkById(500, function ($asientos) use ($porOperacion, $persistir, &$resultado) {
                $updates = [];
                foreach ($asientos as $asiento) {
                    $resultado['asientos_revisados']++;
                    $operacion = (int) $asiento->numeroasiento;
                    $nroAsiento = (int) ($porOperacion[$operacion] ?? 0);
                    if ($nroAsiento <= 0) {
                        $resultado['asientos_sin_match']++;
                        if (count($resultado['muestra_sin_match']) < 30) {
                            $resultado['muestra_sin_match'][] = [
                                'id' => (int) $asiento->id,
                                'numeroasiento' => $operacion,
                            ];
                        }

                        continue;
                    }

                    $resultado['asientos_con_match']++;
                    if ((int) ($asiento->anita_nro_asiento ?? 0) === $nroAsiento) {
                        $resultado['asientos_ya_completos']++;

                        continue;
                    }
                    $updates[] = [
                        'id' => (int) $asiento->id,
                        'anita_nro_asiento' => $nroAsiento,
                    ];
                }

                if ($persistir && $updates !== []) {
                    DB::table('asiento')->upsert($updates, ['id'], ['anita_nro_asiento']);
                }
                $resultado['asientos_actualizados'] += count($updates);
            });

        return $resultado;
    }
}
