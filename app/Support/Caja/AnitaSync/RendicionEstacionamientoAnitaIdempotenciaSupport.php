<?php

namespace App\Support\Caja\AnitaSync;

use App\ApiAnita;
use App\Models\Caja\RendicionEstacionamientoCaja;

/**
 * Evita rendgastro / rendvalor huérfanos en Informix por reintentos de rendición del mismo turno.
 *
 * Clave de negocio en Anita: rendg_nro_rend_vta = turno_operativo_estacionamiento_id.
 */
final class RendicionEstacionamientoAnitaIdempotenciaSupport
{
    /**
     * @return list<int> nro_oper distintos en Anita para el turno
     */
    public static function listarNroOperPorTurno(
        ApiAnita $api,
        int $turnoOperativoId,
        int $empresaId,
        string $tipoOper,
        string $tablaCabecera,
        string $sistema,
    ): array {
        if ($turnoOperativoId <= 0 || $empresaId <= 0) {
            return [];
        }

        $where = " WHERE rendg_nro_rend_vta = '".$turnoOperativoId."'"
            ." AND rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionEstacionamientoCabeceraAnitaMapper::texto($tipoOper, 1)."' ";

        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tablaCabecera,
            'campos' => 'rendg_nro_oper',
            'orderBy' => 'rendg_nro_oper asc',
            'whereArmado' => $where,
        ]));

        $numeros = [];
        foreach ($rows as $fila) {
            $n = (int) ($fila->rendg_nro_oper ?? 0);
            if ($n > 0) {
                $numeros[$n] = $n;
            }
        }

        return array_values($numeros);
    }

    /**
     * Elimina cabeceras y valores en Anita del mismo turno salvo el nro_oper canónico.
     */
    public static function eliminarDuplicadosPorTurno(
        ApiAnita $api,
        int $turnoOperativoId,
        int $empresaId,
        string $tipoOper,
        int $nroOperCanonico,
        string $tablaCabecera,
        string $tablaValor,
        string $sistema,
        string $logEvento,
    ): void {
        foreach (self::listarNroOperPorTurno($api, $turnoOperativoId, $empresaId, $tipoOper, $tablaCabecera, $sistema) as $nroOper) {
            if ($nroOper === $nroOperCanonico) {
                continue;
            }

            $api->apiCallEscritura([
                'acc' => 'delete',
                'tabla' => $tablaValor,
                'sistema' => $sistema,
                'whereArmado' => RendicionEstacionamientoValorAnitaMapper::wherePorOperacion($nroOper, $tipoOper),
            ], 'rendvalor delete huerfano turno '.$turnoOperativoId, $logEvento);

            $api->apiCallEscritura([
                'acc' => 'delete',
                'tabla' => $tablaCabecera,
                'sistema' => $sistema,
                'whereArmado' => RendicionEstacionamientoCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
            ], 'rendgastro delete huerfano turno '.$turnoOperativoId, $logEvento);
        }
    }

    /**
     * Resuelve nro_oper canónico y alinea codigo / nro_oper_anita en ERP si hace falta.
     *
     * @return int nro_oper a usar en el bridge (0 si no hay turno)
     */
    public static function resolverYAlinearNroOper(
        ApiAnita $api,
        RendicionEstacionamientoCaja $rendicion,
        string $tipoOper,
        string $tablaCabecera,
        string $sistema,
        string $tablaValor,
        string $logEvento,
    ): int {
        $turnoId = (int) ($rendicion->turno_operativo_estacionamiento_id ?? 0);
        $empresaId = (int) ($rendicion->empresa_id ?? 0);
        if ($turnoId <= 0 || $empresaId <= 0) {
            return (int) ($rendicion->nro_oper_anita
                ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
        }

        $enAnita = self::listarNroOperPorTurno($api, $turnoId, $empresaId, $tipoOper, $tablaCabecera, $sistema);
        $desdeErp = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        $canonico = self::elegirNroOperCanonico($enAnita, $desdeErp);

        if ($canonico > 0 && $enAnita !== []) {
            self::eliminarDuplicadosPorTurno(
                $api,
                $turnoId,
                $empresaId,
                $tipoOper,
                $canonico,
                $tablaCabecera,
                $tablaValor,
                $sistema,
                $logEvento,
            );
        }

        if ($canonico > 0) {
            self::alinearRendicionErp($rendicion, $canonico);
        }

        return $canonico;
    }

    /**
     * @param  list<int>  $enAnita
     */
    private static function elegirNroOperCanonico(array $enAnita, int $desdeErp): int
    {
        if ($enAnita === []) {
            return max(0, $desdeErp);
        }

        if ($desdeErp > 0 && in_array($desdeErp, $enAnita, true)) {
            return $desdeErp;
        }

        return min($enAnita);
    }

    private static function alinearRendicionErp(RendicionEstacionamientoCaja $rendicion, int $nroOper): void
    {
        $actual = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        if ($actual === $nroOper && (string) $rendicion->codigo === (string) $nroOper) {
            return;
        }

        $rendicion->update([
            'nro_oper_anita' => $nroOper,
            'codigo' => (string) $nroOper,
        ]);
        $rendicion->refresh();
    }
}
