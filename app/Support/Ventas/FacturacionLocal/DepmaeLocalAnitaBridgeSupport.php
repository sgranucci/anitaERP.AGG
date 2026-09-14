<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Ventas\LocalVenta;
use App\Support\Stock\DepmaeAnitaBridgeSupport;

/**
 * Lista depmae desde el bridge Anita del local (servidor/IFX propios).
 * No usa el bridge de fábrica / empresa ERP.
 */
final class DepmaeLocalAnitaBridgeSupport
{
    /**
     * @return list<object>
     */
    public static function listar(LocalVenta $local): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => DepmaeAnitaBridgeSupport::tabla(),
            'campos' => self::camposListado(),
            'orderBy' => DepmaeAnitaBridgeSupport::keyField(),
            'servidor' => $local->anitaServidor(),
            'ifx_server' => $local->anitaIfxServer(),
            'sistema' => DepmaeAnitaBridgeSupport::sistema(),
        ];

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    private static function camposListado(): string
    {
        $camposEnv = trim((string) config('stock.depmae_anita_campos_listado', ''));
        if ($camposEnv !== '') {
            return $camposEnv;
        }

        // Ferli / El Bierzo: descripción al final (CSV del bridge parte por "|").
        return 'depm_deposito,depm_maneja_part,depm_cta_contable,depm_desc';
    }
}
