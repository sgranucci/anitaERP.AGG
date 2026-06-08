<?php

namespace App\Support\Sala;

use App\ApiAnita;

/**
 * Consulta número de parte única (recpu_id) en Informix vía bridge Anita.
 */
class RecpunicaAnitaSupport
{
    public static function buscarPorSku(string $sku): ?int
    {
        $sku = str_pad(trim($sku), 13, ' ', STR_PAD_RIGHT);
        if ($sku === '') {
            return null;
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'recpunica',
            'campos' => 'recpu_id, recpu_articulo',
            'whereArmado' => " WHERE TRIM(recpu_articulo) = '".addslashes(trim($sku))."' ",
            'orderBy' => ' ORDER BY recpu_id DESC ',
            'limit' => ' FIRST 1 ',
        ];

        $raw = $apiAnita->apiCall($data);
        $fila = ApiAnita::primeraFilaLista($raw);
        if (! $fila) {
            return null;
        }

        $id = (int) ($fila['recpu_id'] ?? $fila['RECPU_ID'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
