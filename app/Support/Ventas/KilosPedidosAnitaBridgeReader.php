<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use RuntimeException;

/**
 * Una sola lectura bridge de pendmae+pendmov (+ stkmae) para el mail de kilos El Bierzo.
 * Equivale al recorrido batch de l-pedkilo (REPARTO_AGRUP, kilos teóricos).
 */
final class KilosPedidosAnitaBridgeReader
{
    /** Estados Anita de cabecera a excluir (como SUSPENDIDO / ANULADO en l-pedkilo). */
    private const ESTADOS_EXCLUIDOS = ['A', 'S'];

    /**
     * @param  array{fecha_desde: string, fecha_hasta: string, reparto_desde?: string, reparto_hasta?: string}  $filtros
     * @return list<object>
     */
    public function listarLineas(array $filtros): array
    {
        $desde = $this->fechaYmdAAnita((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = $this->fechaYmdAAnita((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $where = " WHERE penm_tipo='PED' AND penm_letra='X'"
            ." AND penm_fecha_ent BETWEEN {$desde} AND {$hasta}"
            .' AND penv_tipo=penm_tipo AND penv_letra=penm_letra'
            .' AND penv_sucursal=penm_sucursal AND penv_nro=penm_nro'
            .' AND penv_articulo=stkm_articulo'
            ." AND penv_articulo NOT LIKE 'texto%'"
            .$this->whereReparto((string) ($filtros['reparto_desde'] ?? ''), (string) ($filtros['reparto_hasta'] ?? ''));

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae, pendmov, stkmae',
            // Descripción al final: evita corrimiento si el texto trae '|'.
            'campos' => '
                penm_tipo, penm_letra, penm_sucursal, penm_nro, penm_estado, penm_fecha_ent, penm_expreso,
                penv_reparto, penv_articulo, penv_pieza, penv_cantidad, penv_kilos_reales, penv_orden,
                stkm_desc
            ',
            'whereArmado' => $where,
            'orderBy' => 'penv_reparto, penv_articulo, penv_orden',
        ];

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException('No se pudieron leer kilos Anita (pendmov): '.$parsed['error_lectura']);
        }

        $filas = [];
        foreach ($parsed['filas'] as $row) {
            $estado = strtoupper(trim((string) ($row->penm_estado ?? '')));
            if ($estado !== '' && in_array($estado, self::ESTADOS_EXCLUIDOS, true)) {
                continue;
            }
            $sku = trim((string) ($row->penv_articulo ?? ''));
            if ($sku === '' || strncasecmp($sku, 'texto', 5) === 0) {
                continue;
            }
            $filas[] = $row;
        }

        return $filas;
    }

    private function whereReparto(string $desde, string $hasta): string
    {
        [$desde, $hasta] = KiloPedidoListadoFiltros::normalizarRangoRepartos($desde, $hasta);
        if ($desde === '' && $hasta === '') {
            return '';
        }

        $desdeNum = KiloPedidoListadoFiltros::codigoRepartoANumero($desde !== '' ? $desde : '1') ?? 1;
        $hastaNum = KiloPedidoListadoFiltros::codigoRepartoANumero($hasta !== '' ? $hasta : '999999') ?? 999999;
        if ($desdeNum > $hastaNum) {
            [$desdeNum, $hastaNum] = [$hastaNum, $desdeNum];
        }

        // penv_reparto es el del movimiento; penm_expreso respalda cabecera.
        return " AND ((penv_reparto BETWEEN {$desdeNum} AND {$hastaNum})"
            ." OR (penv_reparto=0 AND penm_expreso BETWEEN {$desdeNum} AND {$hastaNum}))";
    }

    private function fechaYmdAAnita(string $ymd): int
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return 0;
        }
        if (preg_match('/^\d{8}$/', $ymd)) {
            return (int) $ymd;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return (int) ($m[1].$m[2].$m[3]);
        }

        return 0;
    }
}
