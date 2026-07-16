<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

use App\ApiAnita;

/**
 * Numerador Anita compartido: rendgastro / rendmaquina / rendbingo deben ser únicos.
 *
 * Estacionamiento (y gastronomía sin piso dedicado) deben tomar el MAX de las tres
 * cabeceras; si solo miran rendgastro, colisionan con máquinas/bingo y mezclan rendvalor.
 */
final class RendicionAnitaNroOperUnificadoSupport
{
    /**
     * @return array{gastro: int, maquina: int, bingo: int, maximo: int}
     */
    public static function maximosPorTabla(ApiAnita $api, int $empresaId, string $sistema = 'caja'): array
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida para numeración Anita unificada.');
        }

        $gastro = self::maxColumna(
            $api,
            $sistema,
            'rendgastro',
            'rendg_nro_oper',
            " WHERE rendg_empresa = '".$empresaId."' ",
        );
        $maquina = self::maxColumna(
            $api,
            $sistema,
            'rendmaquina',
            'rendm_nro_oper',
            " WHERE rendm_empresa = '".$empresaId."' ",
        );
        $bingo = self::maxColumna(
            $api,
            $sistema,
            'rendbingo',
            'rendb_nro_oper',
            " WHERE rendb_empresa = '".$empresaId."' ",
        );

        return [
            'gastro' => $gastro,
            'maquina' => $maquina,
            'bingo' => $bingo,
            'maximo' => max($gastro, $maquina, $bingo),
        ];
    }

    public static function maxNroOperEnAnita(ApiAnita $api, int $empresaId, string $sistema = 'caja'): int
    {
        return self::maximosPorTabla($api, $empresaId, $sistema)['maximo'];
    }

    public static function existeEnAlgunaCabecera(
        ApiAnita $api,
        int $nroOper,
        string $sistema = 'caja',
        ?int $empresaId = null,
    ): bool {
        if ($nroOper <= 0) {
            return false;
        }

        foreach (
            [
                ['rendgastro', 'rendg_nro_oper', 'rendg_empresa'],
                ['rendmaquina', 'rendm_nro_oper', 'rendm_empresa'],
                ['rendbingo', 'rendb_nro_oper', 'rendb_empresa'],
            ] as [$tabla, $colNro, $colEmp]
        ) {
            $where = " WHERE {$colNro} = '".$nroOper."' ";
            if ($empresaId !== null && $empresaId > 0) {
                $where .= " AND {$colEmp} = '".$empresaId."' ";
            }
            $rows = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $colNro,
                'whereArmado' => $where,
            ]));
            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }

    private static function maxColumna(
        ApiAnita $api,
        string $sistema,
        string $tabla,
        string $columna,
        string $where,
    ): int {
        $rows = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $columna,
            'orderBy' => $columna.' desc',
            'whereArmado' => $where,
        ]));

        if ($rows === []) {
            return 0;
        }

        return max(0, (int) ($rows[0]->{$columna} ?? 0));
    }
}
