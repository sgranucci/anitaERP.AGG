<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\ApiAnita;

final class ComprobanteProveedorAnitaNroInternoSupport
{
    public function siguiente(): int
    {
        $maxCompra = $this->maxCampo('compra', 'com_nro_interno');
        $maxPromov = $this->maxCampo('promov', 'prov_nro_interno');

        return max($maxCompra, $maxPromov) + 1;
    }

    private function maxCampo(string $tabla, string $campo): int
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => $tabla,
            'campos' => 'MAX('.$campo.') AS max_nro',
            'whereArmado' => ' WHERE 1=1 ',
        ]);

        $rows = ApiAnita::decodificarListaFilas($raw);
        if ($rows === []) {
            return 0;
        }

        $row = (array) $rows[0];

        return (int) ($row['max_nro'] ?? $row['MAX'] ?? $row[strtolower('max_nro')] ?? 0);
    }
}
