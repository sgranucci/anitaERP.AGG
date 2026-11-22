<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use RuntimeException;

/**
 * Una sola lectura bridge: clientes distintos con pedidos Anita en el rango de entrega.
 * Equivale al recorrido batch de l-pedrem (pendmae / pendmov) sin imprimir el listado.
 */
final class ControlAfipPedidosAnitaBridgeReader
{
    /** @var list<string> */
    private const ESTADOS_EXCLUIDOS = ['A', 'S'];

    /**
     * @param  array{fecha_desde: string, fecha_hasta: string}  $filtros
     * @return list<string> códigos cliente Anita (sin ceros a la izquierda)
     */
    public function listarCodigosCliente(array $filtros): array
    {
        $desde = $this->fechaYmdAAnita((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = $this->fechaYmdAAnita((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => 'penm_cliente, penm_estado, penm_fecha_ent, penm_tipo, penm_letra',
            'whereArmado' => " WHERE penm_tipo='PED' AND penm_letra='X'"
                ." AND penm_fecha_ent BETWEEN {$desde} AND {$hasta}",
            'orderBy' => 'penm_cliente',
        ];

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException('No se pudieron leer pedidos Anita (pendmae): '.$parsed['error_lectura']);
        }

        $codigos = [];
        foreach ($parsed['filas'] as $row) {
            $estado = strtoupper(trim((string) ($row->penm_estado ?? '')));
            if ($estado !== '' && in_array($estado, self::ESTADOS_EXCLUIDOS, true)) {
                continue;
            }
            $codigo = ltrim(trim((string) ($row->penm_cliente ?? '')), '0');
            if ($codigo === '') {
                continue;
            }
            $codigos[$codigo] = true;
        }

        return array_keys($codigos);
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
