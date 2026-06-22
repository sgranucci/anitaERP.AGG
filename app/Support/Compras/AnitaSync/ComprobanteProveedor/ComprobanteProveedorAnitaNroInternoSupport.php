<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\ApiAnita;

final class ComprobanteProveedorAnitaNroInternoSupport
{
    public function siguiente(): int
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'promov',
            'campos' => 'prov_nro_interno',
            'orderBy' => 'prov_nro_interno desc',
        ]);

        $rows = ApiAnita::decodificarListaFilas($raw);
        if ($rows === []) {
            return 1;
        }

        $max = 0;
        foreach ($rows as $row) {
            $n = (int) ($row->prov_nro_interno ?? 0);
            if ($n > $max) {
                $max = $n;
            }
        }

        return $max + 1;
    }
}
